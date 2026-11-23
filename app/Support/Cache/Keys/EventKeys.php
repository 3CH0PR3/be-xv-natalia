<?php

namespace App\Support\Cache\Keys;

final class EventKeys
{
  /**
   * Estado general del evento y reacciones globales
   * TTL: 5 min (300s)
   */
  public static function eventState(string $eventCode): string
  {
    return "event:state:{$eventCode}";
  }

  /**
   * Lista de mesas e invitados del evento
   * TTL: 1 min (60s)
   */
  public static function tables(int|string $eventId): string
  {
    return "tables:event:{$eventId}";
  }

  public static function tablePattern(int|string $eventId): string
  {
    return "tables:event:{$eventId}:*";
  }

  /**
   * Bloqueo / asignación de dispositivo del invitado
   * TTL: 2 min (120s)
   */
  public static function deviceLock(string $deviceId): string
  {
    return "device:lock:{$deviceId}";
  }

  /**
   * Perfil formateado paginado del invitado
   * TTL: 2 min (120s)
   */
  public static function profileData(int|string $eventId, int|string $lockId, int $page = 1): string
  {
    return "profile:data:{$eventId}:{$lockId}:{$page}";
  }

  public static function profilePattern(int|string $eventId, int|string|null $lockId = null): string
  {
    return $lockId
      ? "profile:data:{$eventId}:{$lockId}:*"
      : "profile:data:{$eventId}:*";
  }

  /**
   * Entidad de recuerdo / memoria individual
   * TTL: 5 min (300s)
   */
  public static function memory(string $externalId): string
  {
    return "memory:{$externalId}";
  }

  /**
   * Comentarios de una memoria
   * TTL: 2 min (120s)
   */
  public static function memoryComments(int|string $memoryId): string
  {
    return "memory:comments:{$memoryId}";
  }

  /**
   * Patrón del feed del muro del evento
   */
  public static function feedPattern(int|string $eventId): string
  {
    return "memories:feed:event:{$eventId}:*";
  }

  /**
   * Patrón de todos los comentarios
   */
  public static function commentsPattern(): string
  {
    return 'memory:comments:*';
  }

  /**
   * Lista de versiones de la aplicación móvil / web
   * TTL: 1 hora (3600s)
   */
  public static function appReleases(): string
  {
    return 'app:releases:list';
  }
}
