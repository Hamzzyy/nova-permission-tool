<?php

namespace DigitalCloud\PermissionTool\Resources\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Http\Requests\NovaRequest;

trait RelationshipFieldsPermission
{
    public static function authorizedToViewAny(Request $request)
    {
        $request = app(NovaRequest::class);

        if ($request->viaRelationship()) {
            return true;
        }

        if (! static::authorizable()) {
            return true;
        }

        $gate = Gate::getPolicyFor(static::newModel());

        return ! is_null($gate) && method_exists($gate, 'viewAny')
            ? Gate::check('viewAny', get_class(static::newModel()))
            : true;
    }

    public function detailFields(NovaRequest $request)
    {
        return $this->availableFields($request)
            ->when($request->viaManyToMany(), $this->fieldResolverCallback($request))
            ->when($this->shouldAddActionsField($request), function ($fields) {
                return $fields->push($this->actionfield());
            })
            ->filterForDetail($request, $this->resource)
            ->filter(function ($field) use ($request) {
                return $field->authorizedToSee($request);
            })->values()
            ->resolveForDisplay($this->resource);
    }
}
