<?php

namespace Tests\Factories\Facades;

use App\User;
use Tests\Factories\TaskFactory as BaseTaskFactory;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Task create(array $attributes = [])
 * @method static Collection createMany(int $amount = 1)
 * @method static BaseTaskFactory forUser(User $user)
 * @method static array getRandomTaskData()
 * @mixin BaseTaskFactory
 */
class TaskFactory extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return BaseTaskFactory::class;
    }
}
