<?php
namespace MSM;

interface ApiConnectorInterface
{
    public function key(): string;

    public function label(): string;

    public function testConnection(array $source): array;

    public function discover(array $source): array;
}
