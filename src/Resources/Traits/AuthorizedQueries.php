<?php

namespace DigitalCloud\PermissionTool\Resources\Traits;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;

trait AuthorizedQueries
{
    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        $query->where(function ($query) use ($request) {
            static::actualOwnershipQuery($request, $query);

            static::tempViaRelationshipQuery($request, $query);
            // $query = static::viaRelationshipQuery($request, $query);
        });

        return $query;
    }

    public static function actualOwnershipQuery(NovaRequest $request, $query)
    {
        $resource = static::class;

        // Do not apply on excluded resources
        if (in_array($resource, config('permission.permissions.exclude_resources', []))) {
            return $query;
        }

        // Allow view on all records
        $permission = sprintf('viewAny-%s', $resource);

        if (Gate::check($permission)) {
            return $query;
        }

        // External User is viewing own Record (HasPortal)
        if (Auth::user()?->external && Auth::user()?->userable::class === static::$model && method_exists($query->getModel(), 'user')) {
            return $query->whereHas('user', function ($q) {
                $q->where('users.id', Auth::user()->id);
            });
        }

        // Is User Model.
        // Requires check against id field
        if ($query->getModel()::class == config('permission.models.user')) {
            $query->orWhere('users.id', Auth::user()->id);
        }

        // User is Assigned or Watching the Record
        if (method_exists(static::$model, 'users')) {
            $relation = class_basename(static::$model);
            $relation = Str::camel(Str::plural($relation));

            if (method_exists(User::class, $relation)) {
                $assignedQuery = Auth::user()->$relation()->select(sprintf('%s.*', (new static::$model())->getTable()))->getQuery();
                // $combinedQuery = $assignedQuery;
                $query->orWhereIn('id', function ($query) use ($assignedQuery) {
                    $query->select('subquery.id')->fromSub($assignedQuery, 'subquery');
                });
            } else {
                $query->orWhereHas('users', function ($q) {
                    $q->where('users.id', Auth::user()->id);
                });
            }
        }

        // User Created the Record
        if (method_exists(static::$model, 'owner')) {
            $relation = class_basename(static::$model);
            $relation = 'owned' . Str::title(Str::camel(Str::plural($relation)));

            if (method_exists(User::class, $relation)) {
                $ownedQuery = Auth::user()->$relation()->getQuery();
                // $combinedQuery = $combinedQuery ? $combinedQuery->union($ownedQuery) : $ownedQuery;
                $query->orWhereIn('id', function ($query) use ($ownedQuery) {
                    $query->select('subquery.id')->fromSub($ownedQuery, 'subquery');
                });
            } else {
                $query->orWhere(config('permission.column_names.user_id'), Auth::user()->id);
            }
        }

        return $query;
    }

    public static function tempViaRelationshipQuery(NovaRequest $request, $query)
    {
        if (! auth()->user()?->userable || auth()->user()->userable::class != \App\Models\Client::class) {
            return $query;
        }

        if (static::class == \App\Nova\Load::class) {
            return $query->orWherehas('client', function ($relationshipQuery) use ($request) {
                $relationshipQuery->where(function ($q) use ($request) {
                    \App\Nova\Client::actualOwnershipQuery($request, $q);
                });
            });
        }

        if (static::class == \App\Nova\Invoice::class) {
            return $query->orWhere(function ($query) use ($request) {
                $query->where('invoiceable_type', \App\Models\Client::class)->whereHas('invoiceable', function ($relationshipQuery) use ($request) {
                    // dd($request->viaResourceId);
                    $relationshipQuery->where(function ($q) use ($request) {
                        \App\Nova\Client::actualOwnershipQuery($request, $q);
                    });
                });
            });
        }

        if (static::class == \App\Nova\Payment::class) {
            return $query->orWhereHas('paymentDetails', function ($detailQuery) use ($request) {
                $detailQuery->whereHas('invoice', function ($invoiceQuery) use ($request) {
                    $invoiceQuery->where('invoiceable_type', \App\Models\Client::class)->whereHas('invoiceable', function ($invoiceableQuery) use ($request) {
                        $invoiceableQuery->where(function ($q) use ($request) {
                            \App\Nova\Client::actualOwnershipQuery($request, $q);
                        });
                    });
                });
            });
        }

        return $query;
    }
    /**
     * Include Records can be viewed by the User via it's relationship that the user might own
     * Example: If I user owns (assignee, watcher, owned_by) a client and view it's loads field, then he should be able to view the load.
     *
     * @param NovaRequest $request
     * @param Builder $query
     * @return Builder $query
     */
    public static function viaRelationshipQuery(NovaRequest $request, $query)
    {
        // if (static::class == \App\Nova\User::class) {
        //     return;
        // }

        // $relationships = static::getInversResourceRelationships();

        foreach ($relationships as $relatedResource => $relationship) {
            if ($request->viaRelationship()) {
                if ($request->viaResource() !== $relatedResource) {
                    continue;
                }
            }

            if ($relatedResource && ! method_exists($relatedResource, 'actualOwnershipQuery')) {
                continue;
            }

            if (in_array($relationship, ['assignees', 'watchers', 'owner', 'engagements'])) {
                continue;
            }

            $query->orWhereHas($relationship, function ($relationshipQuery) use ($relatedResource, $request) {
                return $relationshipQuery->where(function ($q) use ($relatedResource, $request) {
                    $relatedResource::actualOwnershipQuery($request, $q);
                });
            });
        }

        return $query;
    }

    /**
     * Only Check if the Record can be viewed by the User via it's relationship that the user might own
     * Example: If I user owns a client and it's loads field, then he should be able to view the load.
     *
     * @param NovaRequest $request
     * @param Builder $query
     * @return bool
     */
    public static function authorizeViaRelationshipQuery(NovaRequest $request, $record)
    {
        $relationships = static::getInversResourceRelationships();
        $query = static::$model::query();

        foreach ($relationships as $relatedResource => $relationship) {
            if ($request->viaRelationship()) {
                if ($request->viaResource() !== $relatedResource) {
                    continue;
                }
            }

            if (in_array($relationship, ['assignees', 'watchers', 'owner', 'engagements'])) {
                continue;
            }

            if (! method_exists($relatedResource, 'actualOwnershipQuery')) {
                continue;
            }

            // $recordExists = $query->orWhereHas($relationship, function ($relationshipQuery) use ($relatedResource, $request, $relationship) {
            //     return $relationshipQuery->where(function ($query) use ($relatedResource, $request, $relationship) {
            //         return $relatedResource::actualOwnershipQuery($request, $query);
            //     });
            // })->exists();

            $recordExists = $record->{$relationship}()->where(function ($q) use ($relatedResource) {
                $q = $relatedResource::actualOwnershipQuery(app(NovaRequest::class), $q);
            })->exists();

            // $recordExists = $relatedResource::actualOwnershipQuery(app(NovaRequest::class), $relatedResource::$model::query())
            //     ->orWhereHas($relationship, function ($query) {
            //         $query->where('id', $record->id);
            //     })->exists();

            if ($recordExists) {
                return true;
            }
        }

        return false;
    }
    public static function tempAuthorizeViaRelationshipQuery(NovaRequest $request, $record)
    {
        $query = static::$model::query();

        if (! auth()->user()?->userable || auth()->user()->userable::class != \App\Models\Client::class) {
            return false;
        }

        if (static::class == \App\Nova\Load::class) {
            return $query->where('id', $record->id)->wherehas('client', function ($relationshipQuery) use ($request) {
                $relationshipQuery->where(function ($q) use ($request) {
                    \App\Nova\Client::actualOwnershipQuery($request, $q);
                });
            })->exists();
        }

        if (static::class == \App\Nova\Invoice::class) {
            return $query->where('id', $record->id)->where(function ($query) use ($request) {
                $query->where('invoiceable_type', \App\Models\Client::class)->whereHas('invoiceable', function ($relationshipQuery) use ($request) {
                    // dd($request->viaResourceId);
                    // $relationshipQuery->
                    $relationshipQuery->where(function ($q) use ($request) {
                        \App\Nova\Client::actualOwnershipQuery($request, $q);
                    });
                });
            })->exists();
        }

        if (static::class == \App\Nova\Payment::class) {
            return $query->where('id', $record->id)->whereHas('paymentDetails', function ($detailQuery) use ($request) {
                $detailQuery->whereHas('invoice', function ($invoiceQuery) use ($request) {
                    $invoiceQuery->where('invoiceable_type', \App\Models\Client::class)->whereHas('invoiceable', function ($invoiceableQuery) use ($request) {
                        $invoiceableQuery->where(function ($q) use ($request) {
                            \App\Nova\Client::actualOwnershipQuery($request, $q);
                        });
                    });
                });
            })->exists();
        }
    }
}
