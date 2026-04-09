<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Container\Container;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

final class ValidationExceptionFactory
{
    /**
     * @param array<string, string|array<int, string>> $messages
     */
    public static function make(array $messages): ValidationException
    {
        $normalized = [];
        foreach ($messages as $key => $value) {
            $normalized[(string) $key] = array_values(array_map(
                static fn ($message) => (string) $message,
                is_array($value) ? $value : [$value]
            ));
        }

        $container = Container::getInstance();
        if ($container instanceof Container && $container->bound('validator')) {
            return ValidationException::withMessages($normalized);
        }

        $container ??= new Container();
        $factory = new ValidatorFactory(new Translator(new ArrayLoader(), 'en'), $container);
        $validator = $factory->make([], []);

        foreach ($normalized as $key => $items) {
            foreach ($items as $message) {
                $validator->errors()->add($key, $message);
            }
        }

        return new ValidationException($validator);
    }
}
