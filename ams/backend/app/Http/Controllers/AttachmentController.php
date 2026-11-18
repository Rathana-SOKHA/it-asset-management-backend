<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function storeForAsset(Request $request, Asset $asset)
    {
        return $this->store($request, ['asset_id' => $asset->id]);
    }

    public function storeForPurchase(Request $request, Purchase $purchase)
    {
        return $this->store($request, ['purchase_id' => $purchase->id]);
    }

    private function store(Request $request, array $parent): Attachment
    {
        $file = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,csv|max:10240',
        ])['file'];
        $path = $file->store('asset-documents', 'public');

        try {
            return Attachment::create($parent + [
                'uploaded_by' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }

    public function download(Request $request, Attachment $attachment)
    {
        abort_unless($attachment->asset_id !== null || $attachment->purchase_id !== null, 404);
        $path = $attachment->path;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download($path, $attachment->original_name);
    }

    public function destroy(Attachment $attachment)
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}
