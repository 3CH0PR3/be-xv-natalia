<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
  /**
   * The path to your application's "home" route.
   *
   * @var string
   */
  public const HOME = '/';

  public function boot(): void
  {
    $this->routes(function (): void {
      $this->mapApiRoutes();
    });
  }

  private function apiGroup(array $middleware, callable $callback): void
  {
    Route::prefix('api/v1')
      ->middleware(array_merge($middleware, ['device.context']))
      ->group($callback);
  }

  protected function mapApiRoutes(): void
  {
    $this->mapEventApiRoutes();
    $this->mapTablesApiRoutes();
    $this->mapMemoriesApiRoutes();
    $this->mapCommentsApiRoutes();
    $this->mapModerationApiRoutes();
    $this->mapReleasesApiRoutes();
    $this->mapProfileApiRoutes();
  }

  protected function mapEventApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/event/api-event.php');
    });
  }

  protected function mapTablesApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/tables/api-tables.php');
    });
  }

  protected function mapMemoriesApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/memories/api-memories.php');
      require base_path('routes/apis/memories/api-upload-operations.php');
    });
  }

  protected function mapCommentsApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/comments/api-comments.php');
    });
  }

  protected function mapModerationApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/moderation/api-moderation.php');
    });
  }

  protected function mapReleasesApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/releases/api-releases.php');
    });
  }

  protected function mapProfileApiRoutes(): void
  {
    $this->apiGroup(['api'], static function (): void {
      require base_path('routes/apis/profile/api-profile.php');
    });
  }
}
