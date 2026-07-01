<?php
namespace MSM;

class EndOfLifeDateClient
{
    private const BASE_URL = 'https://endoflife.date/api/';

    public function fetchProduct(string $product): array
    {
        $product = trim($product);
        if (!preg_match('/^[a-z0-9-]+$/', $product)) {
            throw new \InvalidArgumentException('Produit endoflife.date invalide.');
        }

        $url = self::BASE_URL . $product . '.json';
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "Accept: application/json\r\nUser-Agent: My-Server-Manager\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false || trim($body) === '') {
            throw new \RuntimeException('Impossible de recuperer ' . $url);
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Reponse JSON invalide pour ' . $product);
        }

        return $data;
    }
}
