<?php
declare(strict_types=1);

require_once __DIR__ . '/../autoloader.php';

use MSM\PingStateResolver;

$resolver = new PingStateResolver();

$temporaryFailure = $resolver->resolve('up', 0, 'down', 2);
if ($temporaryFailure['status'] !== 'up' || $temporaryFailure['consecutive_failures'] !== 1 || !$temporaryFailure['pending_failure']) {
    throw new RuntimeException('Un premier echec ne doit pas basculer un serveur sain en down.');
}

$confirmedFailure = $resolver->resolve('up', 1, 'down', 2);
if ($confirmedFailure['status'] !== 'down' || $confirmedFailure['consecutive_failures'] !== 2 || $confirmedFailure['pending_failure']) {
    throw new RuntimeException('Le seuil d echecs consecutifs doit confirmer le statut down.');
}

$customThreshold = $resolver->resolve('up', 1, 'down', 3);
if ($customThreshold['status'] !== 'up' || $customThreshold['consecutive_failures'] !== 2 || !$customThreshold['pending_failure']) {
    throw new RuntimeException('Le seuil configurable doit etre respecte avant de confirmer le statut down.');
}

$recovery = $resolver->resolve('down', 4, 'up', 2);
if ($recovery['status'] !== 'up' || $recovery['consecutive_failures'] !== 0) {
    throw new RuntimeException('Un ping reussi doit restaurer le statut up et remettre le compteur a zero.');
}

echo "PingStateResolverTest: OK\n";
