<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Cases are append-only once any recipient references them: they're stored
 * as raw JSON string values on Recipient::$notification_types, so renaming
 * or removing a case throws on hydration for every row still referencing
 * it. Add new notification types as new cases here, never repurpose one.
 */
enum NotificationType: string implements HasLabel
{
    case ContactFormSubmitted = 'contact_form_submitted';
    case NewProjectPublished = 'new_project_published';

    public function getLabel(): string
    {
        return __('admin.enums.notification_type.'.$this->value);
    }
}
