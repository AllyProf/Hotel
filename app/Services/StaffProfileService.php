<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelSetting;
use App\Models\HotelStaffDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class StaffProfileService
{
    /** @return array<string, mixed> */
    public function hrSettings(Hotel $hotel): array
    {
        $settings = app(HotelSettingsService::class)->ensureDefaults($hotel);

        return is_array($settings->hr) ? $settings->hr : app(HotelSettingsService::class)->defaultHr();
    }

    /** @return list<array{key: string, label: string, required: bool, enabled: bool}> */
    public function enabledDocumentTypes(Hotel $hotel): array
    {
        $types = $this->hrSettings($hotel)['staff_document_types'] ?? [];

        return collect($types)
            ->filter(fn ($row) => ! empty($row['enabled']))
            ->map(fn ($row) => [
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? 'Document'),
                'required' => ! empty($row['required']),
                'enabled' => true,
            ])
            ->filter(fn ($row) => $row['key'] !== '')
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function emailDatalistOptions(Hotel $hotel): array
    {
        $domains = $this->emailSuggestions($hotel);
        $existing = User::query()
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('email')
            ->orderByDesc('id')
            ->limit(25)
            ->pluck('email');

        $placeholders = collect($domains)->map(fn ($d) => 'staff@'.$d);

        return $existing->merge($placeholders)->unique()->values()->all();
    }

    /** @return list<string> */
    public function emailSuggestions(Hotel $hotel): array
    {
        $hr = $this->hrSettings($hotel);
        $hints = $hr['email_domain_hints'] ?? [];
        if (is_string($hints)) {
            $hints = preg_split('/[\s,;]+/', $hints) ?: [];
        }

        $domains = collect($hints)->filter()->map(fn ($d) => ltrim((string) $d, '@'))->all();

        if ($hotel->email && str_contains($hotel->email, '@')) {
            $domains[] = substr(strrchr($hotel->email, '@'), 1);
        }

        $domains = array_merge($domains, ['gmail.com', 'yahoo.com', 'outlook.com']);

        return array_values(array_unique(array_filter($domains)));
    }

    public function syncDocuments(Hotel $hotel, User $staff, Request $request): void
    {
        foreach ($this->enabledDocumentTypes($hotel) as $type) {
            $key = $type['key'];
            $file = $request->file('staff_documents.'.$key);

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('staff-documents/'.$hotel->id.'/'.$staff->id, 'public');

            HotelStaffDocument::query()->updateOrCreate(
                [
                    'user_id' => $staff->id,
                    'document_key' => $key,
                ],
                [
                    'hotel_id' => $hotel->id,
                    'label' => $type['label'],
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]
            );
        }
    }

    /** @return array<string, list<string>> */
    public function validationRulesForDocuments(Hotel $hotel, bool $isEdit, ?User $staff = null): array
    {
        $rules = [];

        foreach ($this->enabledDocumentTypes($hotel) as $type) {
            $key = $type['key'];
            $hasExisting = $staff && HotelStaffDocument::query()
                ->where('user_id', $staff->id)
                ->where('document_key', $key)
                ->exists();

            if ($type['required'] && (! $isEdit || ! $hasExisting)) {
                $rules['staff_documents.'.$key] = ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'];
            } else {
                $rules['staff_documents.'.$key] = ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf'];
            }
        }

        return $rules;
    }

    /** @return list<string|\Illuminate\Validation\Rules\Password> */
    public function passwordValidationRules(Hotel $hotel, bool $isEdit): array
    {
        $hr = $this->hrSettings($hotel);
        $min = max(6, min(32, (int) ($hr['min_password_length'] ?? 8)));

        $rules = [$isEdit ? 'nullable' : 'required', 'string', 'min:'.$min, 'confirmed'];

        if (! empty($hr['require_strong_password'])) {
            $rules[] = \Illuminate\Validation\Rules\Password::min($min)
                ->mixedCase()
                ->numbers();
        }

        return $rules;
    }
}
