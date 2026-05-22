"use client";

import React, { useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { getApiRuntimeDiagnostics } from "@/lib/api/sdk-client";

export default function QrBillPreviewPage() {
  const params = useParams();
  const token = params.token as string;

  const [loading, setLoading] = useState(true);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;

    const fetchBillPreview = async () => {
      try {
        const diagnostics = getApiRuntimeDiagnostics();
        const response = await fetch(`${diagnostics.baseUrl}/api/v1/qr/bill-preview/${token}`);
        const result = await response.json();

        if (!response.ok) {
          setError(result.error?.message || "Failed to load bill preview.");
          return;
        }

        setData(result.data);
      } catch (err: unknown) {
        setError(err instanceof Error ? err.message : "Network error. Please try again later.");
      } finally {
        setLoading(false);
      }
    };

    fetchBillPreview();
  }, [token]);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50">
        <div className="flex flex-col items-center">
          <div className="w-12 h-12 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
          <p className="mt-4 text-gray-600 text-sm">Loading your bill...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50 p-4">
        <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-center space-y-4">
          <div className="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-gray-900">Oops!</h2>
          <p className="text-gray-500">{error}</p>
        </div>
      </div>
    );
  }

  const hasActiveSession = !!data?.reservation_id;
  const table = data?.table;
  const bill = data?.bill_preview;
  const activeOrder = data?.active_order;

  if (!hasActiveSession || !bill) {
    return (
      <div className="flex items-center justify-center min-h-screen bg-gray-50 p-4">
        <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6 text-center space-y-4">
          <div className="w-16 h-16 bg-gray-100 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-gray-900">Table {table?.table_code}</h2>
          <p className="text-gray-500">No active orders or reservations for this table.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 text-gray-900 p-4 sm:p-8">
      <div className="max-w-md mx-auto space-y-6">
        {/* Header */}
        <div className="text-center space-y-2">
          <h1 className="text-2xl font-bold tracking-tight">Your Bill</h1>
          <p className="text-sm text-gray-500 font-medium">Table {table?.table_code}</p>
        </div>

        {/* Bill Summary */}
        <div className="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
          <div className="p-6 bg-gradient-to-br from-indigo-50 to-white border-b border-gray-100">
            <p className="text-sm text-gray-500 mb-1">Total Due</p>
            <div className="flex items-baseline space-x-2">
              <span className="text-3xl font-extrabold tracking-tight">
                {bill.total_due_formatted || `${bill.total_due} ${bill.currency}`}
              </span>
            </div>
          </div>
          
          <div className="p-6 space-y-4">
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Subtotal</span>
              <span className="font-medium text-gray-900">{bill.subtotal_formatted || bill.subtotal}</span>
            </div>
            {bill.discount_amount > 0 && (
              <div className="flex justify-between text-sm text-green-600">
                <span>Discount</span>
                <span className="font-medium">-{bill.discount_amount_formatted || bill.discount_amount}</span>
              </div>
            )}
            <div className="flex justify-between text-sm">
              <span className="text-gray-500">Taxes</span>
              <span className="font-medium text-gray-900">{bill.tax_amount_formatted || bill.tax_amount}</span>
            </div>
            
            <div className="pt-4 mt-4 border-t border-gray-100 border-dashed">
              <button 
                onClick={() => alert("Payment logic will be implemented here (Self-pay Lite).")}
                className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl shadow-md transition-all active:scale-[0.98]"
              >
                Pay Now
              </button>
            </div>
          </div>
        </div>

        {/* Items List (if available in active_order) */}
        {activeOrder?.items && activeOrder.items.length > 0 && (
          <div className="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
            <h3 className="font-semibold text-lg mb-4">Order Items</h3>
            <ul className="space-y-4">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {activeOrder.items.map((item: any) => (
                <li key={item.item_id} className="flex justify-between text-sm">
                  <div>
                    <span className="font-medium text-gray-900">{item.item_name}</span>
                    {item.quantity > 1 && <span className="text-gray-500 ml-2">x{item.quantity}</span>}
                  </div>
                  <span className="text-gray-900 font-medium">
                    {item.price_formatted || item.price}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </div>
  );
}
