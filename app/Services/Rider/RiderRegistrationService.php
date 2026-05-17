<?php

namespace App\Services\Rider;

use App\Models\Rider;
use App\Enums\RiderStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class RiderRegistrationService
{
    public function register(array $data): Rider
    {
        try {
            DB::beginTransaction();

            // Debug received data
            Log::info('Registering rider with data keys:', array_keys($data));
            
            /*
            |--------------------------------------------------------------------------
            | FILE UPLOADS
            |--------------------------------------------------------------------------
            */
            $imagePath   = $this->safeUpload($data['image'] ?? null, 'riders/images');
            $proofPath   = $this->safeUpload($data['utility_bill'] ?? null, 'riders/proofs');
            $licensePath = $this->safeUpload($data['driver_license_image'] ?? null, 'riders/licenses');

            // Debug file paths
            Log::info('File paths saved:', [
                'image' => $imagePath,
                'proof' => $proofPath,
                'license' => $licensePath
            ]);

            /*
            |--------------------------------------------------------------------------
            | CREATE RIDER
            |--------------------------------------------------------------------------
            */
            $riderData = [
                // Personal Info
                'first_name'   => $data['first_name'],
                'last_name'    => $data['last_name'],
                'phone_number' => $data['phone_number'],
                'email'        => $data['email'],
                'gender'       => $data['gender'],
                'address'      => $data['address'],
                'nin'          => $data['nin'],

                // Files (generated paths)
                'image_path'            => $imagePath,
                'proof_of_address_path' => $proofPath,
                'driver_license_path'   => $licensePath,

                // Vehicle Info
                'vehicle_type'          => $data['vehicle_type'],
                'vehicle_color'         => $data['vehicle_color'],
                'vehicle_plate_number'  => $data['vehicle_plate_number'],
                'driver_license_number' => $data['driver_license_number'],
                'vehicle_name'         => $data['vehicle_name'],

                // Guarantor
                'guarantor_name'         => $data['guarantor_name'],
                'guarantor_phone'        => $data['guarantor_phone'],
                'guarantor_relationship' => $data['guarantor_relationship'],
                'guarantor_address'      => $data['guarantor_address'] ?? null,
                'guarantor_occupation'   => $data['guarantor_occupation'] ?? null,

                // Work History
                'previous_place_of_work' => $data['previous_place_of_work'] ?? null,
                'years_of_work'          => $data['years_of_work'] ?? 0,

                // Next of Kin
                'nok_name'         => $data['nok_name'],
                'nok_phone'        => $data['nok_phone'],
                'nok_relationship' => $data['nok_relationship'],
                'nok_address'      => $data['nok_address'] ?? null,

                // Status
                'status' => RiderStatus::PENDING,
            ];

            $rider = Rider::create($riderData);

            DB::commit();

            return $rider;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Rider registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function safeUpload($file, string $directory): ?string
    {
        if (empty($file)) {
            Log::info("No file provided for directory: {$directory}");
            return null;
        }

        Log::info("Processing file for {$directory}", [
            'type' => gettype($file),
            'is_string' => is_string($file),
            'is_uploaded_file' => $file instanceof UploadedFile,
            'value_preview' => is_string($file) ? substr($file, 0, 100) : 'not a string'
        ]);

        // Normal file upload
        if ($file instanceof UploadedFile) {
            $path = $file->store($directory, 'public');
            Log::info("Uploaded file saved to: {$path}");
            return $path;
        }

        // Base64 upload
        if (is_string($file)) {
            // Check if it's base64
            $isBase64 = $this->isBase64($file);
            Log::info("Is base64 string? " . ($isBase64 ? 'Yes' : 'No'));
            
            if ($isBase64) {
                $path = $this->storeBase64File($file, $directory);
                Log::info("Base64 file saved to: {$path}");
                return $path;
            }
        }

        Log::warning("Could not process file for {$directory}");
        return null;
    }

    private function isBase64(string $string): bool
    {
        // Remove data URL prefix if present
        $originalString = $string;
        if (str_contains($string, ',')) {
            $string = explode(',', $string)[1];
        }
        
        // Decode and check if valid
        $decoded = base64_decode($string, true);
        
        // Check if decode was successful AND re-encode matches original
        $isValid = $decoded !== false && base64_encode($decoded) === $string;
        
        if (!$isValid) {
            Log::warning("Invalid base64 string", [
                'starts_with' => substr($originalString, 0, 50)
            ]);
        }
        
        return $isValid;
    }

    private function storeBase64File(string $base64, string $directory): string
    {
        // Remove data URL prefix if present
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            throw new \Exception('Invalid base64 file');
        }

        // Detect file extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $decoded);
        finfo_close($finfo);
        
        $extension = $this->getExtensionFromMimeType($mimeType);
        $fileName = $directory . '/' . uniqid() . '.' . $extension;

        Storage::disk('public')->put($fileName, $decoded);

        return $fileName;
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        return $extensions[$mimeType] ?? 'png';
    }
}