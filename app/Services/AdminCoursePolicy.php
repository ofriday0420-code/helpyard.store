<?php

namespace Helpyard\App\Services;

class AdminCoursePolicy
{
    public const MAX_LESSON_CONTENT_LENGTH = 250000;

    public static function validateSectionTitle(mixed $value): ?string
    {
        return self::text($value, 180);
    }

    public static function validateLesson(array $input): array
    {
        $title = self::text($input['title'] ?? null, 180);
        $content = $input['content'] ?? null;
        if (!is_string($content)) {
            return ['data' => null, 'error' => 'Enter lesson text.'];
        }
        $content = trim($content);
        $contentLength = preg_match_all('/./us', $content);
        if ($content === '' || $contentLength === false || $contentLength > self::MAX_LESSON_CONTENT_LENGTH
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $content)
        ) {
            return ['data' => null, 'error' => 'Lesson text is required and must be 250,000 characters or fewer.'];
        }
        if ($title === null) {
            return ['data' => null, 'error' => 'Enter a lesson title up to 180 characters.'];
        }

        return [
            'data' => [
                'title' => $title,
                'content' => $content,
                'is_published' => ($input['is_published'] ?? null) === '1' ? 1 : 0,
            ],
            'error' => null,
        ];
    }

    private static function text(mixed $value, int $maximum): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        $length = preg_match_all('/./us', $value);
        if ($value === '' || $length === false || $length > $maximum
            || preg_match('/[\x00-\x1F\x7F]/', $value)
        ) {
            return null;
        }

        return $value;
    }
}
