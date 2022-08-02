<?php

namespace Statamic\Eloquent\Forms;

use Statamic\Eloquent\Forms\FormModel as Model;
use Statamic\Events\FormDeleted;
use Statamic\Events\FormSaved;
use Statamic\Facades\File;
use Statamic\Facades\Folder;
use Statamic\Facades\YAML;
use Statamic\Forms\Form as FileEntry;

class Form extends FileEntry
{
    protected $model;

    public static function fromModel(Model $model)
    {
        return (new static())
            ->title($model->title)
            ->handle($model->handle)
            ->store($model->settings['store'] ?? null)
            ->email($model->settings['email'] ?? null)
            ->honeypot($model->settings['honeypot'] ?? null)
            ->model($model);
    }

    public function toModel()
    {
        $class = app('statamic.eloquent.forms.model');

        return $class::findOrNew($this->model?->id)->fill([
            'title' => $this->title(),
            'handle' => $this->handle(),
            'settings' => [
                'store' => $this->store(),
                'email' => $this->email(),
                'honeypot' => $this->honeypot(),
            ],
        ]);
    }

    public function model($model = null)
    {
        if (func_num_args() === 0) {
            return $this->model;
        }

        $this->model = $model;

        return $this;
    }

    public function save()
    {
        $model = $this->toModel();
        $model->save();

        $this->model($model->fresh());

        FormSaved::dispatch($this);
    }

    public function delete()
    {
        $this->submissions()->each->delete();
        $this->model()->delete();

        FormDeleted::dispatch($this);
    }

    public function submissions()
    {
        return $this->model()->submissions()->get()->map(function ($model) {
            $submission = $this->makeSubmission()
                ->id($model->id)
                ->data($model->data);

            $submission
                ->date($model->created_at);

            return $submission;
        });
    }

    public function fileSubmissions()
    {
        $path = config('statamic.forms.submissions').'/'.$this->handle();

        return collect(Folder::getFilesByType($path, 'yaml'))->map(function ($file) {
            return $this->makeSubmission()
                ->id(pathinfo($file)['filename'])
                ->data(YAML::parse(File::get($file)));
        });
    }

    public function submission($id)
    {
        return $this->submissions()->filter(function ($submission) use ($id) {
            return $submission->id() == $id;
        })->first();
    }
}
