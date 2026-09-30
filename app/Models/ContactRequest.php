<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ContactRequest extends Model implements HasMedia
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * Files a visitor can attach to an enquiry: drawings, photos, documents.
     * Extensions only, as the validator cannot detect every CAD format by
     * content. Kept next to the model so the form request and the tests
     * share one list.
     *
     * @var list<string>
     */
    public const ATTACHMENT_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'heic',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'dwg', 'dxf', 'zip',
    ];

    public const MAX_ATTACHMENTS = 5;

    /** Largest single attachment, in kilobytes (matches the media library's 10 MB cap). */
    public const MAX_ATTACHMENT_KILOBYTES = 10240;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'message',
        'status',
        'ip_address',
    ];

    /**
     * Visitor uploads live on the private disk: they are only ever opened
     * from the admin panel, never linked from the public site.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('local');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactRequestStatus::class,
        ];
    }
}
