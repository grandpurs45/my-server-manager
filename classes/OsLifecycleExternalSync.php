<?php
namespace MSM;

class OsLifecycleExternalSync
{
    private const DEFAULT_PRODUCTS = [
        'alpine' => 'alpine',
        'ubuntu' => 'ubuntu',
        'debian' => 'debian',
        'rocky' => 'rocky-linux',
    ];

    public function __construct(
        private readonly OsLifecycleRepository $repository,
        private readonly EndOfLifeDateClient $client = new EndOfLifeDateClient(),
        private readonly ?array $products = null
    ) {
    }

    public static function defaultProducts(): array
    {
        return self::DEFAULT_PRODUCTS;
    }

    public static function parseProductsText(?string $text): array
    {
        if ($text === null) {
            return self::DEFAULT_PRODUCTS;
        }

        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $products = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$family, $product] = explode('=', $line, 2);
            $family = self::normalizeFamily($family);
            $product = self::normalizeProduct($product);

            if ($family === null || $product === null) {
                continue;
            }

            $products[$family] = $product;
        }

        return $products;
    }

    public static function productsToText(array $products): string
    {
        ksort($products);

        $lines = [];
        foreach ($products as $family => $product) {
            $family = self::normalizeFamily((string) $family);
            $product = self::normalizeProduct((string) $product);
            if ($family === null || $product === null) {
                continue;
            }

            $lines[] = $family . '=' . $product;
        }

        return implode("\n", $lines);
    }

    public static function normalizeFamily(string $family): ?string
    {
        $family = strtolower(trim($family));
        $family = str_replace([' ', '-'], '_', $family);

        return preg_match('/^[a-z0-9_]+$/', $family) ? $family : null;
    }

    public static function normalizeProduct(string $product): ?string
    {
        $product = strtolower(trim($product));

        return preg_match('/^[a-z0-9-]+$/', $product) ? $product : null;
    }

    public function supportedProducts(): array
    {
        return $this->products ?? self::DEFAULT_PRODUCTS;
    }

    public function sync(?string $family = null): array
    {
        $products = $this->supportedProducts();
        if ($family !== null && $family !== '') {
            if (!isset($products[$family])) {
                throw new \InvalidArgumentException('Famille non supportee par la synchronisation externe.');
            }

            $products = [$family => $products[$family]];
        }

        $summary = [];
        foreach ($products as $osFamily => $product) {
            $count = 0;
            foreach ($this->client->fetchProduct($product) as $cycle) {
                if (!is_array($cycle)) {
                    continue;
                }

                $reference = $this->mapCycle($osFamily, $product, $cycle);
                if ($reference === null) {
                    continue;
                }

                $this->repository->upsertImportedReference($reference);
                $count++;
            }

            $summary[$osFamily] = $count;
        }

        return $summary;
    }

    private function mapCycle(string $osFamily, string $product, array $cycle): ?array
    {
        $version = trim((string) ($cycle['cycle'] ?? ''));
        if ($version === '') {
            return null;
        }

        $eol = $cycle['eol'] ?? null;
        if ($eol === false || $eol === true || $eol === null || trim((string) $eol) === '') {
            $supportEndsAt = null;
        } else {
            $supportEndsAt = (string) $eol;
        }

        $codename = trim((string) ($cycle['codename'] ?? ''));
        $latest = trim((string) ($cycle['latest'] ?? ''));

        return [
            'os_family' => $osFamily,
            'os_version' => $version,
            'os_codename' => $codename !== '' ? $codename : null,
            'support_ends_at' => $supportEndsAt,
            'source' => 'endoflife.date/' . $product,
            'notes' => $latest !== '' ? 'Derniere version connue: ' . $latest : null,
        ];
    }
}
