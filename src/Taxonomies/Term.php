<?php

namespace Statamic\Eloquent\Taxonomies;

use Statamic\Contracts\Taxonomies\Term as Contract;
use Statamic\Eloquent\Taxonomies\TermModel as Model;
use Statamic\Taxonomies\Term as FileEntry;

class Term extends FileEntry
{
    protected $model;

    public static function fromModel(Model $model)
    {
        $data = $model->data;

        /** @var Term $term */
        $term = (new static())
            ->slug($model->slug)
            ->taxonomy($model->taxonomy)
            ->model($model)
            ->blueprint($model->data['blueprint'] ?? null);

        collect($data['localizations'] ?? [])
            ->except($term->defaultLocale())
            ->each(function ($localeData, $locale) use ($term) {
                $term->dataForLocale($locale, $localeData);
            });

        unset($data['localizations']);

        if (isset($data['collection'])) {
            $term->collection($data['collection']);
            unset($data['collection']);
        }

        $term->data($data);

        return $term;
    }

    public function toModel()
    {
        return self::makeModelFromContract($this);
    }

    public static function makeModelFromContract(Contract $source)
    {
        $class = app('statamic.eloquent.terms.model');

        $data = $source->data();

        if (! isset($data['template'])) {
            unset($data['template']);
        }

        if ($source->blueprint && $source->taxonomy()->termBlueprints()->count() > 1) {
            $data['blueprint'] = $source->blueprint;
        }

        $data['localizations'] = $source->localizations()->keys()->reduce(function ($localizations, $locale) use ($source) {
            $localizations[$locale] = $source->dataForLocale($locale)->toArray();

            return $localizations;
        }, []);

        if ($collection = $source->collection()) {
            $data['collection'] = $collection;
        }

        $isFileEntry = get_class($source) == FileEntry::class;

        return $class::findOrNew($isFileEntry ? null : $source->model?->id)
            ->fill([
                'site' => $source->locale(),
                'slug' => $source->slug(),
                'uri' => $source->uri(),
                'taxonomy' => $source->taxonomy(),
                'data' => $data,
            ]);
    }

    public function model($model = null)
    {
        if (func_num_args() === 0) {
            return $this->model;
        }

        $this->model = $model;

        $this->id($model->id);

        return $this;
    }

    public function lastModified()
    {
        return $this->model?->updated_at;
    }
}
