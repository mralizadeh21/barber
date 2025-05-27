package com.example.mywebviewapp;

import android.os.Bundle;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.appcompat.app.AppCompatActivity;

public class MainActivity extends AppCompatActivity {

    private WebView webView;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webview);
        webView.setWebViewClient(new WebViewClient()); // جلوگیری از باز شدن لینک‌ها در مرورگر خارجی
        webView.getSettings().setJavaScriptEnabled(true); // فعال‌سازی جاوا اسکریپت
        webView.loadUrl("https://mohmadreza.ct.ws"); // آدرس وب‌سایت مورد نظر
    }

    @Override
    public void onBackPressed() {
        if (webView.canGoBack()) {
            webView.goBack(); // برگرداندن به صفحه قبلی در وب ویو
        } else {
            super.onBackPressed(); // اگر صفحه‌ای برای برگشت وجود ندارد، از اکتیویتی خارج شو
        }
    }
}