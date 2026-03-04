<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class DocumentUpload extends Component
{
    use WithFileUploads;

    public $document;

    public ?string $storedPath = null;

    public ?string $storedDisk = null;

    public ?string $downloadUrl = null;

    protected function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    protected array $messages = [
        'document.max' => 'El archivo excede el limite de 5 MB.',
        'document.mimes' => 'Solo se permiten archivos JPG, PNG o PDF.',
    ];

    public function upload(): void
    {
        $this->validate();

        $disk = (string) config('filesystems.documents_disk', 'public');
        $path = $this->document->store('documents/demo', $disk);

        $this->storedDisk = $disk;
        $this->storedPath = $path;
        $this->downloadUrl = Storage::disk($disk)->url($path);

        $this->reset('document');
    }

    public function render()
    {
        return view('livewire.document-upload');
    }
}
