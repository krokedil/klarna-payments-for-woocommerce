<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;
use Symfony\Component\Process\Process;

/**
 * Boots an ngrok HTTPS tunnel in front of BuiltInServerController for the
 * lifetime of a Codeception suite, then shuts it down. Klarna's JS SDK and API
 * refuse non-HTTPS origins, which the PHP built-in server cannot provide.
 *
 * Requires `domain`, `publicUrl` and `forwardPort` in extensions.config. Optional:
 * `authtoken`, `waitForOnlineSeconds` (15), `apiUrl` (http://127.0.0.1:4040).
 */
class NgrokController extends Extension
{
    public static array $events = [
        Events::SUITE_BEFORE => 'start',
        Events::SUITE_AFTER  => 'stop',
    ];

    private ?Process $process = null;

    public function start(): void
    {
        if ($this->process instanceof Process && $this->process->isRunning()) {
            // Already started for an earlier suite in the same run; reuse it.
            return;
        }

        $domain      = (string) ($this->config['domain']      ?? '');
        $publicUrl   = (string) ($this->config['publicUrl']   ?? '');
        $forwardPort = (string) ($this->config['forwardPort'] ?? '');
        $authtoken   = (string) ($this->config['authtoken']   ?? '');
        $waitSeconds = (int)    ($this->config['waitForOnlineSeconds'] ?? 15);
        $apiUrl      = rtrim((string) ($this->config['apiUrl'] ?? 'http://127.0.0.1:4040'), '/');

        if ($domain === '' || $publicUrl === '' || $forwardPort === '') {
            throw new ExtensionException(
                $this,
                'NgrokController requires domain, publicUrl, and forwardPort to be set in extensions.config.'
            );
        }

        // Reuse a tunnel that is already up rather than killing a process we did not start.
        if ($this->tunnelMatchingPublicUrl($apiUrl, $publicUrl) !== null) {
            $this->writeln("NgrokController: tunnel for {$publicUrl} already up; reusing it.");
            return;
        }
        if ($this->ngrokApiReachable($apiUrl)) {
            throw new ExtensionException(
                $this,
                "An ngrok process is already running on {$apiUrl} but is not serving {$publicUrl}. "
                . "Stop it (e.g. `pkill ngrok`) before running the test suite."
            );
        }

        $command = ['ngrok', 'http', '--domain=' . $domain, '--log=stdout', '--log-format=json', $forwardPort];
        $env     = [];
        if ($authtoken !== '') {
            $env['NGROK_AUTHTOKEN'] = $authtoken;
        }

        $this->writeln("NgrokController: starting `" . implode(' ', $command) . "` ...");

        $this->process = new Process($command, null, $env);
        $this->process->setTimeout(null);
        $this->process->setIdleTimeout(null);
        $this->process->start();

        $deadline = microtime(true) + $waitSeconds;
        $lastErr  = '';
        while (microtime(true) < $deadline) {
            if (! $this->process->isRunning()) {
                throw new ExtensionException(
                    $this,
                    "ngrok exited before reaching `online` (code " . $this->process->getExitCode() . "). "
                    . "stderr: " . trim($this->process->getErrorOutput()) . ", "
                    . "stdout: " . trim($this->process->getOutput())
                );
            }
            if ($this->tunnelMatchingPublicUrl($apiUrl, $publicUrl) !== null) {
                $this->writeln("NgrokController: tunnel online at {$publicUrl}.");
                return;
            }
            $lastErr = (string) $this->process->getErrorOutput();
            usleep(250_000); // 250ms
        }

        // Timeout. Kill the doomed process and report what we saw.
        $stderr = trim($lastErr ?: $this->process->getErrorOutput());
        $stdout = trim($this->process->getOutput());
        $this->stop();
        throw new ExtensionException(
            $this,
            "ngrok did not expose {$publicUrl} within {$waitSeconds}s. "
            . ($stderr !== '' ? "stderr: {$stderr}, " : '')
            . ($stdout !== '' ? "stdout: {$stdout}" : '')
        );
    }

    public function stop(): void
    {
        if (! $this->process instanceof Process) {
            return;
        }
        if ($this->process->isRunning()) {
            $this->writeln('NgrokController: stopping tunnel ...');
            $this->process->stop(5.0, defined('SIGTERM') ? SIGTERM : 15);
        }
        $this->process = null;
    }

    private function ngrokApiReachable(string $apiUrl): bool
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $body = @file_get_contents($apiUrl . '/api/tunnels', false, $ctx);
        return $body !== false;
    }

    private function tunnelMatchingPublicUrl(string $apiUrl, string $expectedPublicUrl): ?array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);
        $body = @file_get_contents($apiUrl . '/api/tunnels', false, $ctx);
        if ($body === false) {
			echo "NgrokController: could not reach ngrok API at {$apiUrl} to check tunnels.\n";
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data) || ! isset($data['tunnels']) || ! is_array($data['tunnels'])) {
			echo "NgrokController: unexpected response from ngrok API at {$apiUrl} when checking tunnels: {$body}\n";
            return null;
        }

        foreach ($data['tunnels'] as $tunnel) {
            $public = $tunnel['public_url'] ?? '';
            $proto  = $tunnel['proto']      ?? '';
            if ($public === $expectedPublicUrl && $proto === 'https') {
                return $tunnel;
            }
        }

        return null;
    }
}
