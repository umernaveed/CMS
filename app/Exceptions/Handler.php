<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //a
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($e instanceof PostTooLargeException) {
            $message = 'The submitted form is too large for the server to accept. '
                .'For shipment intake, upload fewer or smaller documents and try again. '
                .'Current server request limit: '.$this->formattedIniSize('post_max_size').'.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'documents' => [$message],
                    ],
                ], 413);
            }

            return response()->view('errors.post-too-large', [
                'message' => $message,
                'postMaxSize' => $this->formattedIniSize('post_max_size'),
                'uploadMaxSize' => $this->formattedIniSize('upload_max_filesize'),
                'previousUrl' => url()->previous(),
            ], 413);
        }

        return parent::render($request, $e);
    }

    private function formattedIniSize(string $key): string
    {
        $value = trim((string) ini_get($key));

        if ($value === '') {
            return 'unknown';
        }

        if (is_numeric($value)) {
            return number_format((float) $value / 1024 / 1024, 2).' MB';
        }

        $unit = strtoupper(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'G' => number_format($number * 1024, 2).' MB',
            'M' => number_format($number, 2).' MB',
            'K' => number_format($number / 1024, 2).' MB',
            default => $value,
        };
    }
}
