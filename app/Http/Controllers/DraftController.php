<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DraftService;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    protected $draftService;

    public function __construct(DraftService $draftService)
    {
        $this->draftService = $draftService;
    }

    /**
     * GET /api/customer/drafts
     */
    public function index(Request $request)
    {
        try {
            $drafts = $this->draftService->getUserDrafts($request->user()->id);

            return response()->json([
                'success' => true,
                'data'    => $drafts,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/customer/drafts/auto-save
     */
    public function autoSave(Request $request)
    {
        $validated = $request->validate([
            'order_id'        => 'nullable|exists:orders,order_id',
            'step'            => 'required|in:pickup,dropoff,item,review',
            'pickup_address'  => 'nullable|string',
            'dropoff_address' => 'nullable|string',
            'item_name'       => 'nullable|string|max:255',
            'package_image'   => 'nullable|url',
            'meta'            => 'nullable|array',
        ]);

        try {
            $draft = $this->draftService->autoSave(
                $request->user()->id,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Draft auto-saved',
                'data'    => [
                    'order_id' => $draft->order_id,
                    'step'     => $draft->step,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * GET /api/customer/drafts/{order_id}
     */
    public function show(Request $request, $orderId)
    {
        try {
            $draft = $this->draftService->resume($request->user()->id, $orderId);

            return response()->json([
                'success' => true,
                'data'    => [
                    'order_id'        => $draft->order_id,
                    'step'            => $draft->step,
                    'pickup_address'  => $draft->pickup_address,
                    'dropoff_address' => $draft->dropoff_address,
                    'item_name'       => $draft->item_name,
                    'package_image'   => $draft->package_image,
                    'meta'            => $draft->meta,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * POST /api/customer/drafts/{order_id}/submit
     */
    public function submit(Request $request, $orderId)
    {
        $validated = $request->validate([
            'pickup_latitude'   => 'required|numeric',
            'pickup_longitude'  => 'required|numeric',
            'dropoff_latitude'  => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
            'sender_name'       => 'required|string',
            'sender_phone'      => 'required|string',
            'receiver_name'     => 'required|string',
            'receiver_phone'    => 'required|string',
        ]);

        try {
            $order = $this->draftService->submitDraft(
                $request->user()->id,
                $orderId,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'data'    => [
                    'order_id' => $order->order_id,
                    'status'   => $order->status,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /**
     * DELETE /api/customer/drafts/{order_id}
     */
    public function destroy(Request $request, $orderId)
    {
        try {
            $this->draftService->deleteDraft($request->user()->id, $orderId);

            return response()->json([
                'success' => true,
                'message' => 'Draft deleted',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(\Exception $e)
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    }
}
