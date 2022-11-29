<?php

namespace Laravel\Reverb\Loggers;

use Laravel\Reverb\Contracts\Logger;

class StandardLogger implements Logger
{
    /**
     * Log an infomational message.
     *
     * @param  string  $title
     * @param  string|null  $message
     * @return void
     */
    public function info(string $title, ?string $message = null): void
    {
        $output = $title;

        if ($message) {
            $output .= ': '.$message;
        }

        fwrite(STDOUT, $output.PHP_EOL);
    }

    /**
     * Log an error message.
     *
     * @param  string  $message
     * @return void
     */
    public function error(string $string): void
    {
        fwrite(STDERR, $string.PHP_EOL);
    }

    /**
     * Append a new line to the log.
     *
     * @param  int  $lines
     * @return void
     */
    public function line(?int $lines = 1): void
    {
        //
    }

    /**
     * Log a message sent to the server.
     *
     * @param  string  $message
     * @return void
     */
    public function message(string $message): void
    {
        $message = json_decode($message, true);

        if (isset($message['data']['channel_data'])) {
            $message['data']['channel_data'] = json_decode($message['data']['channel_data'], true);
        }

        $message = json_encode($message, JSON_PRETTY_PRINT);

        fwrite(STDOUT, $message.PHP_EOL);
    }
}
