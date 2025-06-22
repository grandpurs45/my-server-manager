<?php
require_once __DIR__ . '/version.php';
$version = getVersionFromPackageJson();
?>

<footer class="text-center text-sm text-gray-400 mt-8">
    <p>My Server Manager – Version <?= htmlspecialchars($version) ?></p>
</footer>