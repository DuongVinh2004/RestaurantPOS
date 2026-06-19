"use client";

import { useEffect, useState } from "react";
import { useSearchParams, useRouter } from "next/navigation";
import { CheckCircle2, XCircle, Clock } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";

export default function PaymentCallbackPage() {
  const searchParams = useSearchParams();
  const router = useRouter();
  
  // VNPay returns vnp_ResponseCode=00 for success
  const responseCode = searchParams.get("vnp_ResponseCode");
  const amount = searchParams.get("vnp_Amount");
  
  const status = responseCode === "00" ? "success" : (responseCode ? "error" : "processing");
  const message = responseCode === "00" 
    ? `Thanh toán thành công số tiền ${amount ? (parseInt(amount) / 100).toLocaleString() : ""} VNĐ.` 
    : (responseCode ? "Thanh toán thất bại hoặc đã bị hủy." : "Đang kiểm tra trạng thái thanh toán...");

  return (
    <div className="flex items-center justify-center min-h-[70vh] p-4">
      <Card className="w-full max-w-md shadow-lg border-t-4 border-t-primary">
        <CardHeader className="text-center">
          <div className="flex justify-center mb-4">
            {status === "processing" && <Clock className="w-16 h-16 text-blue-500 animate-pulse" />}
            {status === "success" && <CheckCircle2 className="w-16 h-16 text-green-500" />}
            {status === "error" && <XCircle className="w-16 h-16 text-red-500" />}
          </div>
          <CardTitle className="text-2xl">Kết quả thanh toán</CardTitle>
          <CardDescription className="text-base mt-2">
            {status === "success" && "Giao dịch của bạn đã được ghi nhận hệ thống."}
            {status === "error" && "Giao dịch không thành công."}
          </CardDescription>
        </CardHeader>
        <CardContent className="text-center">
          <p className="text-gray-700 font-medium">{message}</p>
          
          {status === "success" && (
            <div className="mt-6 text-sm text-gray-500 bg-gray-50 p-3 rounded text-left">
              <p><strong>Lưu ý Test:</strong> Vì bạn đang chạy hệ thống ở môi trường Localhost (127.0.0.1), máy chủ VNPAY không thể gọi Webhook về máy bạn để tự động cập nhật trạng thái đơn đặt bàn thành &quot;Đã cọc&quot;. Khi deploy lên server thật, bước này sẽ diễn ra hoàn toàn tự động.</p>
            </div>
          )}
        </CardContent>
        <CardFooter className="flex justify-center pt-4">
          <Button 
            className="w-full" 
            onClick={() => router.push("/")}
            size="lg"
          >
            Quay về trang chủ
          </Button>
        </CardFooter>
      </Card>
    </div>
  );
}
