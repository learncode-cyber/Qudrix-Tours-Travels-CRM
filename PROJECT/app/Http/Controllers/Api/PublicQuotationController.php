<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Package;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public Quotation Controller
 * Used by website to request custom quotes
 */
class PublicQuotationController extends Controller
{
    /**
     * Request a custom quotation
     * 
     * POST /api/v1/quotations
     * 
     * Body:
     * {
     *   "package_id": 1,
     *   "customer": {
     *     "name": "John Doe",
     *     "email": "john@example.com",
     *     "phone": "1234567890"
     *   },
     *   "number_of_travelers": 4,
     *   "travel_date": "2024-12-01",
     *   "special_requirements": "Need hotel near airport",
     *   "budget": "350000"
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|integer|exists:packages,id',
                'customer.name' => 'required|string|min:2|max:255',
                'customer.email' => 'required|email',
                'customer.phone' => 'required|string|min:7|max:20',
                'number_of_travelers' => 'required|integer|min:1|max:50',
                'travel_date' => 'required|date|after:today',
                'special_requirements' => 'nullable|string|max:1000',
                'budget' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Get package
            $package = Package::where('id', $request->package_id)
                ->where('is_active', true)
                ->first();

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package not found',
                    'code' => 'PACKAGE_NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            // Find or create customer
            $customer = Customer::where('email', $request->customer['email'])->first();
            
            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer['name'],
                    'email' => $request->customer['email'],
                    'phone' => $request->customer['phone'],
                    'source' => 'website',
                    'status' => 'lead',
                ]);
            }

            // Calculate base quotation price
            $basePrice = $package->price * $request->number_of_travelers;
            $discount = 0;
            
            // Apply group discounts
            if ($request->number_of_travelers >= 10) {
                $discount = $basePrice * 0.10; // 10% discount
            } elseif ($request->number_of_travelers >= 5) {
                $discount = $basePrice * 0.05; // 5% discount
            }

            $totalPrice = $basePrice - $discount;

            // Create quotation
            $quotation = Quotation::create([
                'quotation_number' => 'QT-' . Str::random(10),
                'package_id' => $package->id,
                'customer_id' => $customer->id,
                'travel_date' => $request->travel_date,
                'number_of_travelers' => $request->number_of_travelers,
                'base_price' => $basePrice,
                'discount_amount' => $discount,
                'total_price' => $totalPrice,
                'special_requirements' => $request->special_requirements ?? null,
                'quoted_budget' => $request->budget ?? null,
                'status' => 'pending_review',
                'valid_until' => now()->addDays(7),
                'created_by' => 'website_api',
            ]);

            // Log API usage
            \Log::info('Quotation requested via public API', [
                'quotation_id' => $quotation->id,
                'package_id' => $package->id,
                'customer_email' => $customer->email,
                'travelers' => $request->number_of_travelers,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quotation request received successfully',
                'data' => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'status' => $quotation->status,
                    'base_price' => $quotation->base_price,
                    'discount_amount' => $quotation->discount_amount,
                    'total_price' => $quotation->total_price,
                    'currency' => 'BDT',
                    'number_of_travelers' => $quotation->number_of_travelers,
                    'valid_until' => $quotation->valid_until->toIso8601String(),
                    'created_at' => $quotation->created_at->toIso8601String(),
                ],
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'api_version' => 'v1',
                    'next_step' => 'Our team will review and send detailed quotation within 24 hours',
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            \Log::error('Public quotation creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create quotation',
                'code' => 'CREATION_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get quotation details
     * 
     * GET /api/v1/quotations/{number}
     * 
     * @param string $number
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($number)
    {
        try {
            $quotation = Quotation::where('quotation_number', $number)->first();

            if (!$quotation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found',
                    'code' => 'NOT_FOUND',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'package_name' => $quotation->package->name ?? null,
                    'customer_name' => $quotation->customer->name ?? null,
                    'status' => $quotation->status,
                    'travel_date' => $quotation->travel_date->toIso8601String(),
                    'number_of_travelers' => $quotation->number_of_travelers,
                    'base_price' => $quotation->base_price,
                    'discount_amount' => $quotation->discount_amount,
                    'discount_percentage' => $quotation->discount_amount > 0 
                        ? round(($quotation->discount_amount / $quotation->base_price) * 100, 2)
                        : 0,
                    'total_price' => $quotation->total_price,
                    'currency' => 'BDT',
                    'price_per_person' => round($quotation->total_price / $quotation->number_of_travelers, 2),
                    'valid_until' => $quotation->valid_until->toIso8601String(),
                    'special_requirements' => $quotation->special_requirements,
                    'created_at' => $quotation->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch quotation',
                'code' => 'FETCH_ERROR',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
