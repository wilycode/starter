<?php

declare(strict_types=1);

namespace App\Form;

use WilyCode\Translation\TranslatorInterface;
use WilyCode\Validator\Error;

final readonly class ValidationErrorFormatter
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function format(Error $error): string
    {
        $options = [];
        foreach ($error->getOptions() as $name => $value) {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $value = $value->name;
            }
            if (is_scalar($value) || $value === null) {
                $options[$name] = $value;
            }
        }

        $code = $error->getCode();
        $key = str_starts_with($code, 'validation.')
            ? substr($code, strlen('validation.'))
            : $code;

        $message = $this->translator->translate('validation', $key, $options);

        return $message === 'validation.' . $key
            ? $this->translator->translate('validation', 'custom.invalid')
            : $message;
    }
}
