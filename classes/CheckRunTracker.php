<?php
namespace MSM;

use Throwable;

class CheckRunTracker
{
    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly string $category
    ) {
    }

    public function start(): void
    {
        $now = $this->now();
        $this->set('check_last_attempt_at', $now);
        $this->set('check_last_status', 'running');
        $this->set('check_last_message', 'Execution en cours.');
    }

    public function skip(string $message): void
    {
        $this->set('check_last_finished_at', $this->now());
        $this->set('check_last_status', 'skipped');
        $this->set('check_last_message', $message);
    }

    public function success(string $message = 'Execution terminee.'): void
    {
        $now = $this->now();
        $this->set('check_last_run_at', $now);
        $this->set('check_last_finished_at', $now);
        $this->set('check_last_status', 'success');
        $this->set('check_last_message', $message);
    }

    public function failure(Throwable $exception): void
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            $message = $exception::class;
        }

        $this->set('check_last_finished_at', $this->now());
        $this->set('check_last_status', 'error');
        $this->set('check_last_message', mb_substr($message, 0, 240));
    }

    private function set(string $key, string $value): void
    {
        $this->settingsManager->set($this->category, $key, $value);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
