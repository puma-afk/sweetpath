<?php
/**
 * Devuelve el estado de la tienda según config:
 * - manual_pause (y mensaje)
 * - manual_pause_until
 * - horario (start/end)
 * - timezone
 *
 * Retorna:
 * [
 *   'is_open' => bool,
 *   'reason' => 'PAUSED'|'OUT_OF_HOURS'|null,
 *   'message' => string|null
 * ]
 */
function store_status(PDO $pdo): array {
  $c = $pdo->query("SELECT * FROM config WHERE id=1 LIMIT 1")->fetch();
  if (!$c) {
    return ['is_open' => true, 'reason' => null, 'message' => null];
  }

  $tz = $c['timezone'] ?: 'America/La_Paz';
  $now = new DateTime('now', new DateTimeZone($tz));

  // 1) Pausa manual con "hasta"
  $manualPause = (int)($c['manual_pause'] ?? 0) === 1;
  $pauseUntil = $c['manual_pause_until'] ?? null;

  if ($manualPause) {
    // Si hay pausa hasta y ya pasó, se considera abierta (pero manual_pause sigue true hasta que lo apaguen)
    if ($pauseUntil) {
      try {
        $until = new DateTime($pauseUntil, new DateTimeZone($tz));
        if ($now < $until) {
          return [
            'is_open' => false,
            'reason' => 'PAUSED',
            'message' => $c['manual_pause_message'] ?: 'No estamos atendiendo en este momento.'
          ];
        }
      } catch (Throwable $e) {
        // si fecha inválida, igual queda pausado
      }
    } else {
      return [
        'is_open' => false,
        'reason' => 'PAUSED',
        'message' => $c['manual_pause_message'] ?: 'No estamos atendiendo en este momento.'
      ];
    }
  }

  // 2) Horario de atención
  $start = $c['business_hours_start'] ?? '08:00:00';
  $end   = $c['business_hours_end'] ?? '20:00:00';

  $startDt = new DateTime($now->format('Y-m-d') . ' ' . $start, new DateTimeZone($tz));
  $endDt   = new DateTime($now->format('Y-m-d') . ' ' . $end, new DateTimeZone($tz));

  // Si el horario cruza medianoche (ej: 20:00 a 02:00)
  if ($endDt <= $startDt) {
    $endDt->modify('+1 day');
  }

  $isOpen = ($now >= $startDt && $now <= $endDt);

  if (!$isOpen) {
    return [
      'is_open' => false,
      'reason' => 'OUT_OF_HOURS',
      'message' => 'Fuera de horario de atención. Vuelve en nuestro horario 😊'
    ];
  }

  return ['is_open' => true, 'reason' => null, 'message' => null];
}