<?php

namespace App\Http\Controllers;

use App\Models\KhqrTransaction; // Import the KhqrTransaction model
use Illuminate\Http\Request;
use Illuminate\Support\Str; // For generating unique reference numbers
use GuzzleHttp\Client; // Import Guzzle HTTP client for external API calls
use Carbon\Carbon; // For handling dates, especially expires_at

class KhqrPaymentController extends Controller
{
    /**
     * Method to generate KHQR string parameters and initiate payment with external bank/PSP.
     * This method is called by the frontend (React).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateKhqr(Request $request)
    {
        // 1. Validate incoming request data from the frontend
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency_code' => 'required|string|size:3', // e.g., 'KHR' or 'USD'
            'sale_id' => 'nullable|integer|exists:sales,id', // Link to your existing sales table
            // Add any other necessary validation rules for data sent from frontend
        ]);

        $amount = $request->input('amount');
        $currencyCode = $request->input('currency_code');
        $saleId = $request->input('sale_id'); // Optional: Link to a specific sale

        // Generate a unique reference number for this transaction within your POS system
        $referenceNumber = 'POS-KHQR-' . Str::uuid();

        // 2. Prepare Parameters for KHQR String Generation (based on KHQR SDK Document)
        // These values should ideally come from your application's settings (e.g., from the 'settings' table)
        // and the current transaction details.
        $merchantName = config('app.name'); // Or from your settings table
        $merchantCity = 'Phnom Penh'; // Or from your settings table
        $countryCode = 'KH'; // Cambodia

        // !!! IMPORTANT: These IDs come from your registration with a specific bank/PSP for KHQR
        // You MUST get these from your bank/PSP's merchant portal or documentation.
        $merchantID = env('KHQR_MERCHANT_ID'); // Example: '1234567890123456'
        $bakongAccountID = env('KHQR_BAKONG_ACCOUNT_ID'); // Example: 'your_pos@bank.com'
        $acquiringBank = env('KHQR_ACQUIRING_BANK'); // Example: 'ABA Bank' or 'ACLEDA Bank'

        if (!$merchantID || !$bakongAccountID || !$acquiringBank) {
            return response()->json(['message' => 'KHQR merchant credentials not configured in .env'], 500);
        }

        // The KHQR SDK document provides parameters for Individual and Merchant QR.
        // You'll need to decide which type you're generating.
        // For a POS, 'Merchant' type is usually more appropriate.
        $khqrParams = [
            'merchantName' => $merchantName,
            'merchantCity' => $merchantCity,
            'countryCode' => $countryCode,
            'amount' => $amount,
            'currency' => $currencyCode,
            'billNumber' => $referenceNumber, // Use your internal reference as bill number for tracing
            'merchantID' => $merchantID,
            'bakongAccountID' => $bakongAccountID,
            'acquiringBank' => $acquiringBank,
            // Add other optional parameters as needed from the KHQR SDK document (e.g., storeLabel, terminalLabel)
        ];

        // 3. Generate KHQR String (Using the Javascript SDK on Frontend is often easier)
        // The KHQR SDK document shows Javascript examples for generating the string.
        // It's often simpler to send the `khqrParams` to the frontend and let the
        // React app use the `bakong-khqr` JS SDK to generate the actual QR string.
        // If you want to generate the QR string on the backend, you'd need a PHP library
        // for EMVCo QR code generation, which is outside the scope of the provided SDK.
        $khqrString = null; // Initialize as null, frontend will generate or bank will provide

        // 4. Initiate Payment with External Bank/PSP API (CRITICAL & EXTERNAL INTEGRATION)
        // This is the most complex part. You need to call the actual bank/PSP's API
        // to register this payment request and get a transaction ID from them.
        // Use GuzzleHttp\Client for this.
        $bankTransactionId = null;
        $bankResponseData = null;

        try {
            $client = new Client();
            $bankApiEndpoint = env('KHQR_BANK_API_ENDPOINT'); // e.g., 'https://api.abapayway.com/v1/payments'
            $bankApiKey = env('KHQR_BANK_API_KEY');

            if (!$bankApiEndpoint || !$bankApiKey) {
                return response()->json(['message' => 'Bank API endpoint or key not configured in .env'], 500);
            }

            // Example call to a hypothetical bank API (replace with actual bank's API structure)
            $apiResponse = $client->post($bankApiEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bankApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => $amount,
                    'currency' => $currencyCode,
                    'merchant_reference' => $referenceNumber, // Your internal reference
                    'callback_url' => env('APP_URL') . '/api/khqr/callback', // Your webhook URL
                    // ... other parameters required by the bank's API
                ],
            ]);

            $bankResponseData = json_decode($apiResponse->getBody()->getContents(), true);

            // Assuming the bank API returns a transaction ID and possibly the KHQR string
            $bankTransactionId = $bankResponseData['transaction_id'] ?? null;
            if (isset($bankResponseData['khqr_string'])) {
                $khqrString = $bankResponseData['khqr_string']; // If bank provides the final QR string
            }

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Handle HTTP client errors (e.g., 4xx responses from bank API)
            $responseBody = $e->getResponse()->getBody()->getContents();
            \Log::error('KHQR Bank API client error: ' . $responseBody);
            return response()->json(['message' => 'Bank API error: ' . json_decode($responseBody)->message ?? 'Unknown client error'], $e->getCode());
        } catch (\Exception $e) {
            // Handle other general exceptions
            \Log::error('KHQR Bank API connection error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to connect to bank API.'], 500);
        }

        // 5. Store Transaction in your database
        $khqrTransaction = KhqrTransaction::create([
            'sale_id' => $saleId,
            'khqr_string' => $khqrString, // Store the string (if generated/provided by bank)
            'amount' => $amount,
            'currency_code' => $currencyCode,
            'reference_number' => $referenceNumber,
            'bank_transaction_id' => $bankTransactionId,
            'status' => 'pending', // Initial status
            'response_data' => json_encode($bankResponseData), // Store full response for debugging
            'expires_at' => Carbon::now()->addMinutes(10), // Example: QR valid for 10 minutes
        ]);

        // 6. Return response to the frontend
        return response()->json([
            'message' => 'KHQR generation requested successfully.',
            'reference_number' => $referenceNumber,
            'khqr_string' => $khqrString, // Send the string to the frontend to render the QR
            'khqr_params' => $khqrParams, // Send params if frontend needs to generate QR string
        ]);
    }

    /**
     * Method for frontend to poll payment status.
     *
     * @param  string  $reference  The internal reference number for the transaction.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentStatus($reference)
    {
        $transaction = KhqrTransaction::where('reference_number', $reference)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        // In a real production system, you might call the bank/PSP API here
        // to get the latest status if you don't rely solely on webhooks.
        // For simplicity, we're returning the status from our database.

        return response()->json([
            'reference_number' => $transaction->reference_number,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency_code,
        ]);
    }

    /**
     * Method to handle webhook/callback from the bank/PSP.
     * This endpoint should be publicly accessible and protected by signature verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallback(Request $request)
    {
        // !!! VERY IMPORTANT: Implement robust signature verification here !!!
        // The bank/PSP will send a signature (e.g., in headers or payload) with the callback.
        // You MUST verify this signature against a shared secret to ensure the callback
        // is legitimate and not a malicious request.
        // Example (conceptual, refer to your bank's API docs for actual implementation):
        // $signature = $request->header('X-Bank-Signature');
        // if (! YourBankApiHelper::verifySignature($request->getContent(), $signature, env('KHQR_BANK_WEBHOOK_SECRET'))) {
        //     \Log::warning('Invalid KHQR callback signature.');
        //     return response()->json(['message' => 'Unauthorized'], 401);
        // }

        $payload = $request->all(); // The entire payload from the bank/PSP
        \Log::info('Received KHQR callback: ' . json_encode($payload)); // Log for debugging

        // Extract relevant data from the bank's payload (adjust keys based on actual bank API)
        $bankTransactionId = $payload['transaction_id'] ?? null;
        $status = $payload['status'] ?? 'unknown'; // 'completed', 'failed', 'refunded', etc.
        $referenceNumber = $payload['merchant_ref'] ?? null; // Your internal reference number from the bank's payload

        if (!$referenceNumber || !$bankTransactionId) {
            \Log::error('Invalid KHQR callback payload: Missing reference_number or transaction_id. Payload: ' . json_encode($payload));
            return response()->json(['message' => 'Invalid callback payload.'], 400);
        }

        // Find the transaction in your database
        $transaction = KhqrTransaction::where('reference_number', $referenceNumber)
                                    ->where('bank_transaction_id', $bankTransactionId)
                                    ->first();

        if (!$transaction) {
            \Log::warning('Unmatched KHQR callback received. Reference: ' . $referenceNumber . ', Bank Txn ID: ' . $bankTransactionId);
            return response()->json(['message' => 'Transaction not found for callback.'], 404);
        }

        // Update transaction status based on the callback
        $transaction->status = $status;
        $transaction->response_data = json_encode($payload); // Store the full callback payload for auditing
        $transaction->save();

        // Perform any other necessary actions:
        // - Update the associated 'sale' record's payment status.
        // - Send a receipt to the customer.
        // - Update inventory.
        // - Trigger notifications.

        return response()->json(['message' => 'Callback received and processed successfully.']);
    }
}