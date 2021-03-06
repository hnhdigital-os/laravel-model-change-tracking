<?php

namespace Bluora\LaravelModelChangeTracking;

use App;
use Auth;
use Config;

trait LogStateChangeTrait
{
    /**
     * Log the state.
     *
     * @param string $model
     * @param mixed  $state
     *
     * @return void
     */
    private static function addModelStateChange($model, $state)
    {
        $models_path = Config::get('model_change_tracking.ModelsPath');
        $log_model_name = Config::get('model_change_tracking.LogModelStateChange');
        $model_id_name = config('model_change_tracking.log-model-state-change.model-id', 'model_id');

        $log = new $log_model_name();
        $log->model = str_replace($models_path, '', static::class);
        $log->$model_id_name = $model->getKey();
        $log->state = $state;
        $log->log_by = null;
        if (!App::runningInConsole() && Auth::check()) {
            $log->log_by = Auth::user()->getKey();
        }
        $log->ip_address = request()->ip();
        $log->save();
    }

    /**
     * Boot the events that apply which user is making the last event change.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.NpathComplexity)
     */
    public static function bootLogStateChangeTrait()
    {
        static::created(function ($model) {
            static::addModelStateChange($model, 'created');
        });

        static::updated(function ($model) {
            static::addModelStateChange($model, 'updated');
        });

        if (method_exists(get_class(), 'deleted')) {
            static::deleted(function ($model) {
                static::addModelStateChange($model, 'deleted');
            });
        }

        if (method_exists(get_class(), 'restored')) {
            static::restored(function ($model) {
                static::addModelStateChange($model, 'restored');
            });
        }
    }

    /**
     * Return the user that created this model.
     *
     * @return User
     */
    public function getCreatedByAttribute()
    {
        $log = $this->stateChange()
            ->where('state', 'created')
            ->first();

        if (!is_null($log)) {
            return $log->logBy;
        }
    }

    /**
     * Return the user that updated this model.
     *
     * @return User
     */
    public function getUpdatedByAttribute()
    {
        $log = $this->stateChange()
            ->where('state', 'updated')
            ->orderBy('log_at', 'DESC')
            ->first();

        if (!is_null($log)) {
            return $log->logBy;
        }
    }

    /**
     * Return the user that deleted this model.
     *
     * @return User
     */
    public function getDeletedByAttribute()
    {
        $log = $this->stateChange()
            ->where('state', 'deleted')
            ->orderBy('log_at', 'DESC')
            ->first();

        if (!is_null($log)) {
            return $log->logBy;
        }
    }

    /**
     * Return the user that restored this model.
     *
     * @return User
     */
    public function getRestoredByAttribute()
    {
        $log = $this->stateChange()
            ->where('state', 'restored')
            ->orderBy('log_at', 'DESC')
            ->first();

        if (!is_null($log)) {
            return $log->logBy;
        }
    }

    /**
     * Get the logs for this model.
     *
     * @return Collection
     */
    public function stateChange()
    {
        $model_id_name = config('model_change_tracking.log-model-state-change.model-id', 'model_id');
        $model_other_id_name = config('model_change_tracking.log-model-state-change.model-other-id', 'id');

        return $this->hasMany(config('model_change_tracking.LogModelStateChange'), $model_id_name, $model_other_id_name)
            ->where('model', studly_case($this->table))
            ->orderBy('log_at');
    }
}
