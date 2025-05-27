<?php
header('Content-Type: application/json; charset=utf-8');

// --- شروع بخش تنظیمات و اتصال ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // <<<--- نام کاربری پایگاه داده خود را وارد کنید
define('DB_PASS', '');             // <<<--- رمز عبور پایگاه داده خود را وارد کنید
define('DB_NAME', 'barbershop_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Tehran');
// --- پایان بخش تنظیمات و اتصال ---

$response = ['success' => false, 'message' => 'درخواست نامعتبر.'];
$action = $_REQUEST['action'] ?? null;
$json_data = json_decode(file_get_contents('php://input'), true);

function sanitize_input($conn, $data) {
    return htmlspecialchars(stripslashes(trim($conn->real_escape_string($data))));
}

switch ($action) {
    case 'book':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $firstName = sanitize_input($conn, $_POST['firstName'] ?? '');
            $lastName = sanitize_input($conn, $_POST['lastName'] ?? '');
            $phone = sanitize_input($conn, $_POST['phone'] ?? '');
            $service = sanitize_input($conn, $_POST['service'] ?? '');
            $date = sanitize_input($conn, $_POST['date'] ?? '');
            $time = sanitize_input($conn, $_POST['time'] ?? '');
            $message = sanitize_input($conn, $_POST['message'] ?? '');

            if (empty($firstName) || empty($lastName) || empty($phone) || empty($service) || empty($date) || empty($time)) {
                $response['message'] = 'لطفاً تمام فیلدهای اصلی را پر کنید.';
            } elseif (!preg_match("/^09[0-9]{9}$/", $phone)) {
                 $response['message'] = 'فرمت شماره تلفن صحیح نیست.';
            } elseif (!preg_match("/^[1-4][0-9]{3}\/(0[1-9]|1[0-2])\/(0[1-9]|[1-2][0-9]|3[0-1])$/", $date)) {
                 $response['message'] = 'فرمت تاریخ شمسی صحیح نیست (مثال: ۱۴۰۳/۰۳/۰۵).';
            } elseif (!preg_match("/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/", $time)) {
                 $response['message'] = 'فرمت زمان صحیح نیست (مثال: ۱۶:۳۰).';
            } else {
                $stmt_check = $conn->prepare("SELECT id FROM appointments WHERE app_date = ? AND app_time = ? AND status = 'confirmed'");
                $stmt_check->bind_param("ss", $date, $time);
                $stmt_check->execute();
                $stmt_check->store_result();

                if ($stmt_check->num_rows > 0) {
                    $response['message'] = 'متاسفانه این زمان قبلاً رزرو شده است.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO appointments (first_name, last_name, phone, service, app_date, app_time, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $firstName, $lastName, $phone, $service, $date, $time, $message);
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'نوبت شما با موفقیت ثبت شد و در انتظار تایید است.';
                    } else {
                        $response['message'] = 'خطا در ثبت نوبت.';
                    }
                    $stmt->close();
                }
                $stmt_check->close();
            }
        }
        break;

    case 'check_status':
        if (isset($_GET['phone'])) {
            $phone = sanitize_input($conn, $_GET['phone']);
            if (!preg_match("/^09[0-9]{9}$/", $phone)) {
                 $response['message'] = 'فرمت شماره تلفن صحیح نیست.';
            } else {
                $stmt = $conn->prepare("SELECT app_date, app_time, service, status, barber_message FROM appointments WHERE phone = ? ORDER BY created_at DESC");
                $stmt->bind_param("s", $phone);
                $stmt->execute();
                $result = $stmt->get_result();
                $appointments = [];
                while ($row = $result->fetch_assoc()) {
                    $appointments[] = $row;
                }
                $response['success'] = true;
                $response['appointments'] = $appointments;
                $response['message'] = $result->num_rows > 0 ? 'نوبت‌ها یافت شدند.' : 'نوبتی یافت نشد.';
                $stmt->close();
            }
        }
        break;

    case 'get_appointments':
        $filterDate = sanitize_input($conn, $_GET['date'] ?? '');
        $sql = "SELECT id, first_name, last_name, phone, service, app_date, app_time, status FROM appointments";
        $params = [];
        $types = "";

        if (!empty($filterDate)) {
             if (!preg_match("/^[1-4][0-9]{3}\/(0[1-9]|1[0-2])\/(0[1-9]|[1-2][0-9]|3[0-1])$/", $filterDate)) {
                 $response['message'] = 'فرمت تاریخ فیلتر صحیح نیست.';
                 echo json_encode($response);
                 $conn->close();
                 exit();
             }
            $sql .= " WHERE app_date = ?";
            $params[] = $filterDate;
            $types .= "s";
        }
        $sql .= " ORDER BY app_date, app_time";

        $stmt = $conn->prepare($sql);
         if (!empty($params)) {
             $stmt->bind_param($types, ...$params);
         }
        $stmt->execute();
        $result = $stmt->get_result();
        $appointments = [];
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
        $response['success'] = true;
        $response['appointments'] = $appointments;
        $stmt->close();
        break;

    case 'update_status':
        if ($json_data) {
            $id = filter_var($json_data['id'] ?? 0, FILTER_VALIDATE_INT);
            $status = sanitize_input($conn, $json_data['status'] ?? '');
            if ($id && in_array($status, ['confirmed', 'rejected'])) {
                $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $status, $id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'وضعیت به‌روزرسانی شد.';
                } else {
                    $response['message'] = 'خطا در بروزرسانی.';
                }
                $stmt->close();
            }
        }
        break;

    case 'send_message':
        if ($json_data) {
            $id = filter_var($json_data['id'] ?? 0, FILTER_VALIDATE_INT);
            $barber_message = sanitize_input($conn, $json_data['barber_message'] ?? '');
            if ($id) {
                $stmt = $conn->prepare("UPDATE appointments SET barber_message = ? WHERE id = ?");
                $stmt->bind_param("si", $barber_message, $id);
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'پیام ارسال شد.';
                } else {
                    $response['message'] = 'خطا در ارسال پیام.';
                }
                $stmt->close();
            }
        }
        break;
}

$conn->close();
echo json_encode($response);
?>