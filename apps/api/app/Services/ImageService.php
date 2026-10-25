<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function list(Model $imageable): Collection
    {
        return $this->relation($imageable)->get();
    }

    public function attach(Model $imageable, UploadedFile $file, ?int $sortOrder = null): Image
    {
        $disk = config('filesystems.default', 'local');
        $directory = 'images/'.Str::of($imageable::class)->classBasename()->lower();
        $path = $file->store($directory, $disk);

        $order = $sortOrder ?? ((int) $this->relation($imageable)->max('sort_order') + 1);

        return $this->relation($imageable)->create([
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'byte_size' => $file->getSize() ?: 0,
            'sort_order' => $order,
            'created_at' => now(),
        ]);
    }

    public function reorder(Image $image, int $sortOrder): Image
    {
        $image->update(['sort_order' => $sortOrder]);

        return $image->refresh();
    }

    public function delete(Image $image): void
    {
        Storage::disk($image->disk)->delete($image->path);
        $image->delete();
    }

    public function deleteAllFor(Model $imageable): void
    {
        $this->relation($imageable)->get()->each(fn (Image $image) => $this->delete($image));
    }

    /**
     * @return MorphMany<Image, Model>
     */
    private function relation(Model $imageable): MorphMany
    {
        /** @var MorphMany<Image, Model> $relation */
        $relation = $imageable->images();

        return $relation;
    }
}
