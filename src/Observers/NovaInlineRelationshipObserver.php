<?php

namespace KirschbaumDevelopment\NovaInlineRelationship\Observers;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Illuminate\Database\Eloquent\Model;
use KirschbaumDevelopment\NovaInlineRelationship\Integrations\Integrate;
use KirschbaumDevelopment\NovaInlineRelationship\NovaInlineRelationship;
use KirschbaumDevelopment\NovaInlineRelationship\Contracts\RelationshipObservable;
use KirschbaumDevelopment\NovaInlineRelationship\Helpers\NovaInlineRelationshipHelper;

class NovaInlineRelationshipObserver
{
    /**
     * Handle updating event for the model
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function updating(Model $model)
    {
        $this->callEvent($model, 'updating');
    }

    /**
     * Handle updated event for the model
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function created(Model $model)
    {
        $this->callEvent($model, 'created');
    }

    /**
     * Handle updating event for the model
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function creating(Model $model)
    {
        $this->callEvent($model, 'creating');
    }

    /**
     * Handle events for the model
     *
     * @param Model $model
     * @param string $event
     *
     * @return mixed
     */
    public function callEvent(Model $model, string $event)
    {
        $modelClass = get_class($model);

        $relationships = $this->getModelRelationships($model);

        // @note should call the correct class when `nova-inline-relationships.custom` is set.
        $InlineRelationship = config('nova-inline-relationships.custom', false) === false ?
            NovaInlineRelationship::class :
            config('nova-inline-relationships.custom');
        $relatedModelAttribs = $InlineRelationship::$observedModels[$modelClass];

        foreach ($relationships as $relationship) {
            $observer = $this->getRelationshipObserver($model, $relationship);

            if ($observer instanceof RelationshipObservable) {
                $observer->{$event}($model, $relationship, $relatedModelAttribs[$relationship] ?? []);
            }
        }
    }

    /**
     * Checks if a relationship is singular
     *
     * @param Model $model
     * @param $relationship
     *
     * @return RelationshipObservable
     */
    public function getRelationshipObserver(Model $model, $relationship): RelationshipObservable
    {
        $className = NovaInlineRelationshipHelper::getObserver($model->{$relationship}());

        return class_exists($className) ? resolve($className) : null;
    }

    /**
     * @param Model $model
     *
     * @return mixed
     */
    protected function getModelRelationships(Model $model)
    {
        $request = request();
        // @note: we're going to need a NovaRequest instead of using the Illuminate\Http\Request.
        $request = new NovaRequest($request->all());
        $resource = Nova::newResourceFromModel($model);

        // @note: $resource->fields($request) will not work if the first field returned is a package, e.g.  Nova Tabs
        //          or similar packages where the first field returned by `fields()` is a field nesting other fields.
        return collect($resource->availableFields($request))
            ->flatMap(function ($field) use($model) {
                // @note: is this a solution to find inline-relationship fields within third party packages?
                //          if so, to which problem is this a solution?
                return Integrate::fields($field);
            })
            ->filter(function ($value) {
                return (isset($value->component) && $value->component === 'nova-inline-relationship');
            })
            ->pluck('attribute')
            ->all();
    }
}
