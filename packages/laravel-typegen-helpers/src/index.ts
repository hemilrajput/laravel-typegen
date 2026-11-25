// ─────────────────────────────────────────────
// Laravel Pagination
// ─────────────────────────────────────────────

export interface PaginationLinks {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
}

export interface PaginationMeta {
    current_page: number;
    from: number;
    last_page: number;
    path: string;
    per_page: number;
    to: number;
    total: number;
}

/** Matches the JSON shape of a Laravel `paginate()` response. */
export interface PaginatedResponse<T> {
    data: T[];
    links: PaginationLinks;
    meta: PaginationMeta;
}

/** Matches the shape of `simplePaginate()` — no total/last_page. */
export interface SimplePaginatedResponse<T> {
    data: T[];
    links: Pick<PaginationLinks, 'prev' | 'next'>;
}

/** Matches the shape of `cursorPaginate()`. */
export interface CursorPaginatedResponse<T> {
    data: T[];
    path: string;
    per_page: number;
    next_cursor: string | null;
    next_page_url: string | null;
    prev_cursor: string | null;
    prev_page_url: string | null;
}

// ─────────────────────────────────────────────
// Inertia.js Form Helpers
// ─────────────────────────────────────────────

/** Mirrors the Inertia `useForm()` return shape for typed forms (v1 & v2 compatible). */
export interface InertiaForm<TData extends Record<string, unknown>> {
    data: TData;
    errors: Partial<Record<keyof TData, string>>;
    hasErrors: boolean;
    processing: boolean;
    progress: { percentage: number } | null;
    wasSuccessful: boolean;
    recentlySuccessful: boolean;
    isDirty: boolean;
    defaults?: TData | ((field?: keyof TData, value?: unknown) => void);
    setDefaults?: (fieldOrFields?: keyof TData | Partial<TData>, value?: unknown) => void;
    cancel?: () => void;
    reset(...fields: (keyof TData)[]): void;
    clearErrors(...fields: (keyof TData)[]): void;
    setError(field: keyof TData, value: string): void;
    setError(errors: Partial<Record<keyof TData, string>>): void;
    transform(callback: (data: TData) => object): this;
    submit(method: string, url: string, options?: InertiaFormOptions): void;
    get(url: string, options?: InertiaFormOptions): void;
    post(url: string, options?: InertiaFormOptions): void;
    put(url: string, options?: InertiaFormOptions): void;
    patch(url: string, options?: InertiaFormOptions): void;
    delete(url: string, options?: InertiaFormOptions): void;
}

export interface InertiaFormOptions {
    preserveScroll?: boolean;
    preserveState?: boolean;
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
}

// ─────────────────────────────────────────────
// API Resource helpers
// ─────────────────────────────────────────────

/** Wraps a single `JsonResource` response envelope. */
export interface ApiResource<T> {
    data: T;
}

/** Wraps a `ResourceCollection` response envelope. */
export interface ApiResourceCollection<T> {
    data: T[];
}

// ─────────────────────────────────────────────
// Eloquent Relation helpers
// ─────────────────────────────────────────────

/**
 * Mirrors the `Relation<T>` helper emitted by laravel-typegen when
 * `relations.wrap_with_relation` is enabled (e.g. `posts?: Relation<Post[]>`).
 */
export type Relation<T> = T;

/** Narrows a `Relation<T>` to its loaded (non-null, non-undefined) form. */
export type LoadedRelation<T> = NonNullable<T>;

/** Type guard verifying an optional/nullable relation property was eager-loaded and non-null. */
export function isRelationLoaded<T>(value: T | null | undefined): value is NonNullable<T> {
    return value !== undefined && value !== null;
}

/** Unwraps a Laravel `JsonResource` `{ data: T }` response envelope. */
export function unwrapResource<T>(response: ApiResource<T>): T {
    return response.data;
}

// ─────────────────────────────────────────────
// Utility types
// ─────────────────────────────────────────────

/** Makes all properties of T optional recursively. */
export type DeepPartial<T> = {
    [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};

/** Marks specific keys of T as required. */
export type RequireFields<T, K extends keyof T> = T & Required<Pick<T, K>>;

/** Extract the element type from an array. */
export type Unarray<T> = T extends (infer U)[] ? U : T;

/** A record keyed by the string values of an enum or union. */
export type EnumRecord<K extends string, V> = Record<K, V>;
