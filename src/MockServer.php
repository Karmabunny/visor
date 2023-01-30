<?php
namespace karmabunny\visor;

/**
 * A mock server that can return templated responses.
 *
 * @package karmabunny\visor
 */
class MockServer extends Server
{

    /** @var array [path => [headers, data]] */
    public $mocks;


    /** @inheritdoc */
    protected function getTargetScript(): string
    {
        return __DIR__ . '/mock.php';
    }


    /**
     * Get the last payload received by the mock server.
     *
     * @return array|null
     */
    public function getLastPayload(): ?array
    {
        $path = $this->getWorkingPath() . '/latest.json';
        $payload = file_get_contents($path);
        $payload = json_decode($payload, true);
        return $payload;
    }


    /**
     * Create a mock response for this path.
     *
     * @param string $path
     * @param string[] $headers [ name => value ]
     * @param string $body
     * @return void
     */
    public function setMock(string $path, array $headers, string $body)
    {
        // Overwrite whatever exists.
        $path = '/' . ltrim($path, '/');
        $this->mocks[$path] = [
            'headers' => $headers,
            'body' => $body,
        ];

        // Commit.
        $path = $this->getWorkingPath() . '/mocks.json';
        file_put_contents($path, json_encode($this->mocks));
    }


    /** @inheritdoc */
    public function healthCheck(): bool
    {
        $path = '/' . uniqid() . '.json';
        $rando1 = uniqid();
        $rando2 = uniqid();

        $expected_res = json_encode(['mock' => $rando1]);
        $this->setMock($path, [], $expected_res);

        // Ping it.
        $test_url = $this->getHostUrl() . $path;
        $this->log("testing: {$test_url}");

        $actual_res = file_get_contents($test_url . '?success=' . $rando2);
        $actual_req = $this->getLastPayload();

        $ok = (
            $actual_res === $expected_res
            and $actual_req['query']['success'] === $rando2
        );

        if (!$ok) {
            $this->log('req: ' . json_encode($actual_req));
            $this->log('res: ' . $actual_res);
        }
        else {
            // Remove our mock.
            unset($this->mocks[$path]);
            $path = $this->getWorkingPath() . '/mocks.json';
            file_put_contents($path, json_encode($this->mocks));

            // Trash the testing payload.
            $path = $this->getWorkingPath() . '/latest.json';
            @unlink($path);
        }

        return $ok;
    }
}
