<?php
namespace MSM;

use PDO;

class HardwareHealthRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function saveResult(HardwareHealthCheckResult $result): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO hardware_health_checks (
                    server_id,
                    collector,
                    status,
                    sensors_count,
                    smart_disks_count,
                    max_temperature_celsius,
                    checked_at,
                    duration_ms,
                    error_message,
                    smart_error_message
                ) VALUES (
                    :server_id,
                    :collector,
                    :status,
                    :sensors_count,
                    :smart_disks_count,
                    :max_temperature_celsius,
                    :checked_at,
                    :duration_ms,
                    :error_message,
                    :smart_error_message
                )
            ");
            $stmt->execute([
                ':server_id' => $result->serverId,
                ':collector' => $result->collector,
                ':status' => $result->status,
                ':sensors_count' => count($result->temperatures),
                ':smart_disks_count' => count($result->smartDisks),
                ':max_temperature_celsius' => $result->maxTemperature(),
                ':checked_at' => $result->checkedAt,
                ':duration_ms' => $result->durationMs,
                ':error_message' => $result->errorMessage,
                ':smart_error_message' => $result->smartErrorMessage,
            ]);

            $checkId = (int) $this->pdo->lastInsertId();
            $readingStmt = $this->pdo->prepare("
                INSERT INTO hardware_temperature_readings (
                    hardware_check_id,
                    sensor_key,
                    sensor_label,
                    sensor_type,
                    temperature_celsius
                ) VALUES (
                    :hardware_check_id,
                    :sensor_key,
                    :sensor_label,
                    :sensor_type,
                    :temperature_celsius
                )
            ");

            foreach ($result->temperatures as $reading) {
                $readingStmt->execute([
                    ':hardware_check_id' => $checkId,
                    ':sensor_key' => mb_substr((string) ($reading['key'] ?? 'unknown'), 0, 190),
                    ':sensor_label' => mb_substr((string) ($reading['label'] ?? 'Sonde'), 0, 255),
                    ':sensor_type' => (string) ($reading['type'] ?? 'other'),
                    ':temperature_celsius' => (float) ($reading['temperature'] ?? 0),
                ]);
            }

            $diskStmt = $this->pdo->prepare("
                INSERT INTO hardware_smart_disks (
                    hardware_check_id,
                    device_name,
                    device_type,
                    protocol,
                    model_name,
                    serial_number,
                    capacity_bytes,
                    smart_supported,
                    smart_enabled,
                    smart_passed,
                    temperature_celsius,
                    power_on_hours,
                    percentage_used,
                    media_errors,
                    error_message
                ) VALUES (
                    :hardware_check_id,
                    :device_name,
                    :device_type,
                    :protocol,
                    :model_name,
                    :serial_number,
                    :capacity_bytes,
                    :smart_supported,
                    :smart_enabled,
                    :smart_passed,
                    :temperature_celsius,
                    :power_on_hours,
                    :percentage_used,
                    :media_errors,
                    :error_message
                )
            ");

            foreach ($result->smartDisks as $disk) {
                $diskStmt->execute([
                    ':hardware_check_id' => $checkId,
                    ':device_name' => mb_substr((string) ($disk['device_name'] ?? 'unknown'), 0, 190),
                    ':device_type' => $disk['device_type'] ?? null,
                    ':protocol' => $disk['protocol'] ?? null,
                    ':model_name' => $disk['model_name'] ?? null,
                    ':serial_number' => $disk['serial_number'] ?? null,
                    ':capacity_bytes' => $disk['capacity_bytes'] ?? null,
                    ':smart_supported' => $this->nullableBool($disk['smart_supported'] ?? null),
                    ':smart_enabled' => $this->nullableBool($disk['smart_enabled'] ?? null),
                    ':smart_passed' => $this->nullableBool($disk['smart_passed'] ?? null),
                    ':temperature_celsius' => $disk['temperature_celsius'] ?? null,
                    ':power_on_hours' => $disk['power_on_hours'] ?? null,
                    ':percentage_used' => $disk['percentage_used'] ?? null,
                    ':media_errors' => $disk['media_errors'] ?? null,
                    ':error_message' => $disk['error_message'] ?? null,
                ]);
            }

            $this->pdo->commit();
            return $checkId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getLatestForServer(int $serverId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM hardware_health_checks
            WHERE server_id = :server_id
            ORDER BY checked_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':server_id' => $serverId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function getTemperaturesForCheck(int $checkId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sensor_key, sensor_label, sensor_type, temperature_celsius
            FROM hardware_temperature_readings
            WHERE hardware_check_id = :hardware_check_id
            ORDER BY temperature_celsius DESC, sensor_label ASC
        ");
        $stmt->execute([':hardware_check_id' => $checkId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSmartDisksForCheck(int $checkId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM hardware_smart_disks
            WHERE hardware_check_id = :hardware_check_id
            ORDER BY device_name ASC
        ");
        $stmt->execute([':hardware_check_id' => $checkId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function nullableBool(mixed $value): ?int
    {
        return $value === null ? null : ($value ? 1 : 0);
    }
}
