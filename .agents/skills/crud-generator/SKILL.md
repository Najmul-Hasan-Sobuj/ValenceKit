---
name: crud-generator
description: Standardized blueprint and step-by-step generator for creating production-ready administrative CRUD modules in ValenceKit. Enforces architectural patterns, reusable UI components (Table, Drawer, Dialog, Alert, Badges, Combobox), Spatie RBAC integration, Audit Logging, Eloquent API Resources, Wayfinder routes, Pest tests, and AdminSidebar registration.
---

# Admin CRUD Module Generator Blueprint

This skill serves as the **master blueprint and authoritative checklist** for scaffolding, architecting, and building any new administrative CRUD module in **ValenceKit**.

Whenever the user asks to create, scaffold, or add a new module (e.g., *Projects, Invoices, Subscriptions, Products, Discounts, Leads, Tickets*), follow this skill step-by-step to ensure complete architectural consistency, zero missing files, and seamless integration with existing patterns.

---

## ⚠️ Non-Negotiable Rules & Standards

### 1. Mandatory `php artisan make:` Command Enforcement
> [!CRITICAL]
> **DO NOT create any PHP or migration file manually without an Artisan command.**
> You MUST execute the corresponding `php artisan make:*` command via `run_command` with `--no-interaction` to generate every file skeleton. Only after the file has been scaffolded by Artisan may you edit or overwrite its contents to add the implementation.

| Target File Type | Mandatory Artisan Command |
| :--- | :--- |
| **Migration** | `php artisan make:migration create_<table_name>_table --no-interaction` |
| **Model** | `php artisan make:model <Model> --no-interaction` |
| **Controller** | `php artisan make:controller Admin/<Model>Controller --no-interaction` |
| **Store Request** | `php artisan make:request Admin/<Model>/Store<Model>Request --no-interaction` |
| **Update Request** | `php artisan make:request Admin/<Model>/Update<Model>Request --no-interaction` |
| **Eloquent Resource**| `php artisan make:resource Admin/<Model>Resource --no-interaction` |
| **Model Factory** | `php artisan make:factory <Model>Factory --model=<Model> --no-interaction` |
| **Database Seeder** | `php artisan make:seeder <Model>Seeder --no-interaction` |
| **Pest Feature Test**| `php artisan make:test Admin/<Model>Test --pest --no-interaction` |
| **Generic PHP Class**| `php artisan make:class <ClassName> --no-interaction` |

---

### 2. Strict Drawer Sizing & Positioning Standard
> [!IMPORTANT]
> When creating a form drawer (`<Model>Drawer.vue`), you **MUST** strictly follow the exact sizing, height, and viewport placement used across ValenceKit's existing modules (`CategoryDrawer.vue`, `UserDrawer.vue`, `TransactionDrawer.vue`).

```html
<Drawer :open="isOpen" @update:open="emit('update:isOpen', $event)">
    <DrawerContent class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64">
        <!-- Drawer content -->
    </DrawerContent>
</Drawer>
```

#### Why this exact sizing is required:
- **`md:left-64`**: Aligns the drawer perfectly to the right edge of ValenceKit's desktop sidebar (`w-64` / `16rem`), ensuring the drawer **never obscures or overlaps the admin sidebar navigation**.
- **`md:right-2`**: Provides a clean 8px floating margin from the right edge of the screen for an elevated modern aesthetic.
- **`max-h-[92vh] overflow-y-auto`**: Restricts drawer height to 92% of the viewport and enables smooth vertical scrolling for multi-field forms, preventing layout clipping on laptop screens.
- **Mobile responsiveness**: On screens smaller than `md`, it automatically defaults to a standard bottom sheet.

---

### 3. Strict Adherence to `.agents/` Skills, Rules, and `AGENTS.md`
> [!IMPORTANT]
> You must strictly adhere to the guidelines committed in `AGENTS.md` and the domain-specific skills and rules in `.agents/`. Always activate and follow the corresponding skill for each aspect of development:

- **`AGENTS.md`**: Enforces PHP 8.2+ constructor promotion, explicit parameter/return type declarations, array shape docblocks, curly braces for control structures, and Pint formatting.
- **`laravel-best-practices`**: Follow when writing controllers, FormRequests, models, and Eloquent queries.
- **`wayfinder-development`**: Follow when registering and calling routes. Import typed route functions from `@/routes/` and `@/actions/` (never use hardcoded URLs or Ziggy).
- **`inertia-vue-development`**: Follow when writing Vue pages. Vue components must have a single root element; do not use Axios (use Inertia's built-in client or `useForm`).
- **`tailwindcss-development`**: Follow for utility classes, dark mode variants, and responsive grids.
- **`testing-best-practices`**: Follow for Pest test structure, assertions, and data factories.
- **`admin-module-tester`**: Run after development to verify the new module end-to-end via Chrome DevTools MCP.

---

## 🏛️ The ValenceKit CRUD Architecture Map

Every new administrative module consists of 12 required components across backend, frontend, security, and testing:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   ValenceKit Module Architecture                       │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Database & Persistence                                              │
│    ├── database/migrations/YYYY_MM_DD_create_<table_name>_table.php    │
│    ├── database/factories/<Model>Factory.php                           │
│    └── database/seeders/<Model>Seeder.php                              │
├────────────────────────────────────────────────────────────────────────┤
│ 2. Domain & Authorization                                              │
│    ├── app/Models/<Model>.php (with #[Fillable] & auditLogValues())    │
│    ├── database/seeders/RoleAndPermissionSeeder.php (RBAC Groups)      │
│    └── app/Http/Resources/Admin/<Model>Resource.php                    │
├────────────────────────────────────────────────────────────────────────┤
│ 3. HTTP Layer & Business Logic                                         │
│    ├── app/Http/Requests/Admin/<Model>/Store<Model>Request.php         │
│    ├── app/Http/Requests/Admin/<Model>/Update<Model>Request.php        │
│    ├── app/Http/Controllers/Admin/<Model>Controller.php                │
│    └── routes/admin.php (Named resource routes)                        │
├────────────────────────────────────────────────────────────────────────┤
│ 4. Client-side Presentation (Inertia + Vue 3)                          │
│    ├── resources/js/pages/admin/<slug>/Index.vue (Table, Search, Page) │
│    ├── resources/js/pages/admin/<slug>/<Model>Drawer.vue (Form/Drawer) │
│    ├── resources/js/layouts/admin/AdminSidebar.vue (Nav registration)  │
│    └── resources/js/actions/... (Wayfinder auto-generated routes)      │
├────────────────────────────────────────────────────────────────────────┤
│ 5. Verification & Testing                                              │
│    ├── tests/Feature/Admin/<Model>Test.php (Pest test suite)           │
│    └── php artisan test --compact --filter=<Model>                     │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 📋 Step-by-Step Implementation Protocol

---

### Step 1: Database Migration & Model

1. **Scaffold Migration via Artisan**:
   ```bash
   php artisan make:migration create_<table_name>_table --no-interaction
   ```
   - Standard columns: `id`, `name`, `slug` (if applicable), `description`, `is_active` (boolean, default true), foreign keys, timestamps, softDeletes (if applicable).
   - Add composite indexes for filtered/searched queries (e.g. `$table->index(['is_active', 'created_at']);`).

2. **Scaffold Model via Artisan**:
   ```bash
   php artisan make:model <Model> --no-interaction
   ```
   Update `app/Models/<Model>.php` to enforce:
   - Modern PHP 8.2+ `#[Fillable([...])]` class attribute instead of `$fillable` array.
   - `protected function casts(): array` method for casting (e.g. `'is_active' => 'boolean'`).
   - `public function auditLogValues(): array` for `AuditLogService` logging.
   - `use HasFactory;` trait.

   ```php
   <?php

   namespace App\Models;

   use Database\Factories\<Model>Factory;
   use Illuminate\Database\Eloquent\Attributes\Fillable;
   use Illuminate\Database\Eloquent\Factories\HasFactory;
   use Illuminate\Database\Eloquent\Model;

   #[Fillable(['name', 'slug', 'description', 'is_active'])]
   class <Model> extends Model
   {
       /** @use HasFactory<<Model>Factory> */
       use HasFactory;

       protected function casts(): array
       {
           return [
               'is_active' => 'boolean',
           ];
       }

       /**
        * Attributes to record in audit trail logs.
        *
        * @return array<string, mixed>
        */
       public function auditLogValues(): array
       {
           return $this->only(['name', 'slug', 'is_active']);
       }
   }
   ```

3. **Scaffold Factory & Seeder via Artisan**:
   ```bash
   php artisan make:factory <Model>Factory --model=<Model> --no-interaction
   php artisan make:seeder <Model>Seeder --no-interaction
   ```

---

### Step 2: Role & Permission Registration (RBAC)

Update `database/seeders/RoleAndPermissionSeeder.php`:

1. Add the permission group under `RoleAndPermissionSeeder::GROUPS`:
   ```php
   '<Module> Management' => [
       '<slug>.view' => 'Browse and inspect <models>',
       '<slug>.create' => 'Create new <models>',
       '<slug>.edit' => 'Update <model> details and status',
       '<slug>.delete' => 'Remove <models> from the system',
   ],
   ```
2. Assign default permissions to non-system roles like `Staff` (under `RoleAndPermissionSeeder::ROLES`).
3. Re-seed permissions:
   ```bash
   php artisan db:seed --class=RoleAndPermissionSeeder --no-interaction
   ```

---

### Step 3: Form Requests

Create dedicated Form Requests for `Store` and `Update` operations using the Artisan generator:

```bash
php artisan make:request Admin/<Model>/Store<Model>Request --no-interaction
php artisan make:request Admin/<Model>/Update<Model>Request --no-interaction
```

**Standard Form Request Pattern**:
```php
<?php

namespace App\Http\Requests\Admin\<Model>;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Store<Model>Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') && $this->user('admin')->can('<slug>.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('<table_name>', 'slug')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
```

---

### Step 4: Eloquent API Resource

Always wrap Eloquent models in an API Resource to prevent leaking sensitive model attributes:

```bash
php artisan make:resource Admin/<Model>Resource --no-interaction
```

```php
<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class <Model>Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

---

### Step 5: Controller & Audit Logging

Create the administrative controller via Artisan:
```bash
php artisan make:controller Admin/<Model>Controller --no-interaction
```

**Controller Golden Rules in ValenceKit**:
1. Check `<slug>.view` at the top of `index()`:
   ```php
   abort_unless($request->user('admin')->can('<slug>.view'), 403);
   ```
2. Debounced search & filter support with `paginate(15)->withQueryString()`.
3. Inject `App\Services\AuditLogService` into mutation methods (`store`, `update`, `destroy`):
   - `$auditLogService->created('<slug>.created', $model, description: '...', actor: $request->user('admin'))`
   - `$auditLogService->updated('<slug>.updated', $model, beforeValues: $before, description: '...', actor: $request->user('admin'))`
   - `$auditLogService->deleted('<slug>.deleted', $model, beforeValues: $before, description: '...', actor: $request->user('admin'))`
4. Flash toasts: return back with `with('success', '...')` or `with('error', '...')`.

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\<Model>\Store<Model>Request;
use App\Http\Requests\Admin\<Model>\Update<Model>Request;
use App\Http\Resources\Admin\<Model>Resource;
use App\Models\<Model>;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class <Model>Controller extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('admin')->can('<slug>.view'), 403);

        $search = $request->query('search');
        $status = $request->query('status');

        $query = <Model>::query()
            ->when($search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->when($status !== null && $status !== 'all', function ($q) use ($status) {
                $q->where('is_active', $status === 'active');
            })
            ->latest('id');

        return Inertia::render('admin/<slug>/Index', [
            'items' => <Model>Resource::collection($query->paginate(15)->withQueryString()),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Store<Model>Request $request, AuditLogService $auditLogService)
    {
        $<variable> = <Model>::create($request->validated());

        $auditLogService->created(
            '<slug>.created',
            $<variable>,
            description: '<Model> ' . $<variable>->name . ' was created.',
            actor: $request->user('admin'),
        );

        return back()->with('success', '<Model> created successfully.');
    }

    public function update(Update<Model>Request $request, <Model> $<variable>, AuditLogService $auditLogService)
    {
        $before = $<variable>->auditLogValues();
        $<variable>->update($request->validated());

        $auditLogService->updated(
            '<slug>.updated',
            $<variable>,
            beforeValues: $before,
            description: '<Model> ' . $<variable>->name . ' was updated.',
            actor: $request->user('admin'),
        );

        return back()->with('success', '<Model> updated successfully.');
    }

    public function destroy(Request $request, <Model> $<variable>, AuditLogService $auditLogService)
    {
        abort_unless($request->user('admin')->can('<slug>.delete'), 403);

        $before = $<variable>->auditLogValues();
        $name = $<variable>->name;
        $<variable>->delete();

        $auditLogService->deleted(
            '<slug>.deleted',
            $<variable>,
            beforeValues: $before,
            description: '<Model> ' . $name . ' was deleted.',
            actor: $request->user('admin'),
        );

        return back()->with('success', '<Model> deleted successfully.');
    }
}
```

---

### Step 6: Route Registration & Wayfinder Generation

In `routes/admin.php` (inside `middleware(['auth:admin'])` group):

```php
Route::resource('<slug>', <Model>Controller::class)
    ->except(['create', 'show', 'edit'])
    ->names('admin.<slug>');
```

Run Wayfinder to generate typed TypeScript route functions:
```bash
php artisan wayfinder:generate --no-interaction
```

---

### Step 7: Frontend UI Implementation (Inertia + Vue 3)

Create directory: `resources/js/pages/admin/<slug>/`
1. `Index.vue` (Table, search, pagination, actions)
2. `<Model>Drawer.vue` (Standard responsive side drawer)

#### 📦 Reusable UI Components Catalog in ValenceKit:

Always import existing UI components from `@/components/ui/`:

| Component Type | Import Path | Components | Use Case |
| :--- | :--- | :--- | :--- |
| **Table** | `@/components/ui/table` | `Table`, `TableHeader`, `TableBody`, `TableHead`, `TableRow`, `TableCell` | Data list display |
| **Drawer** | `@/components/ui/drawer` | `Drawer`, `DrawerContent`, `DrawerHeader`, `DrawerTitle`, `DrawerDescription`, `DrawerFooter` | Create & Edit form (must use `md:inset-x-auto md:right-2 md:left-64`) |
| **Dialog** | `@/components/ui/dialog` | `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogFooter` | Alternative to Drawer for quick dialogs |
| **Alert Dialog** | `@/components/ui/alert-dialog` | `AlertDialog`, `AlertDialogAction`, `AlertDialogCancel`, `AlertDialogContent`, `AlertDialogDescription`, `AlertDialogFooter`, `AlertDialogHeader`, `AlertDialogTitle` | Deletion confirmation modal |
| **Alert** | `@/components/ui/alert` | `Alert`, `AlertTitle`, `AlertDescription` | In-page notices/warnings |
| **Button** | `@/components/ui/button` | `Button` | Standard actions & links |
| **Badge** | `@/components/ui/badge` | `Badge` | Status chips (`Active`, `Inactive`) |
| **Input & Label** | `@/components/ui/input`, `@/components/ui/label` | `Input`, `Label` | Form text fields |
| **Select / Switch** | `@/components/ui/select`, `@/components/ui/switch` | `Select`, `SelectTrigger`, `SelectValue`, `SelectContent`, `SelectItem`, `Switch` | Dropdowns & toggles |
| **Input Error** | `@/components/InputError.vue` | `InputError` | Form validation errors |
| **Breadcrumbs** | `defineOptions({ layout: { breadcrumbs: [...] } })` | — | Topbar breadcrumb navigation |
| **Relative Time** | `@/components/RelativeTime.vue` | `RelativeTime` | Formatted created/updated timestamps |
| **Sonner Toast** | `vue-sonner` | `toast.success()`, `toast.error()` | Action notifications |

---

#### 📄 Standard `Index.vue` Template:

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { watchDebounced } from '@vueuse/core';
import { decodePaginationLabel } from '@/lib/decodePaginationLabel';
import { Plus, Search, Pencil, Trash2, MoreHorizontal } from '@lucide/vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { usePermissions } from '@/composables/usePermissions';
import RelativeTime from '@/components/RelativeTime.vue';
import <Model>Drawer from './<Model>Drawer.vue';
import { index as <slug>Route } from '@/routes/admin/<slug>';
import { destroy } from '@/actions/App/Http/Controllers/Admin/<Model>Controller';

const props = defineProps<{
    items: {
        data: Array<{
            id: number;
            name: string;
            description?: string;
            is_active: boolean;
            created_at: string;
        }>;
        meta?: { from: number; to: number; total: number; links: Array<any> };
        links?: Array<any>;
    };
    filters: {
        search?: string;
        status?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: '<Models>', href: <slug>Route.url() }],
    },
});

const { can } = usePermissions();

const searchQuery = ref(props.filters.search || '');

const applyFilters = () => {
    router.visit(<slug>Route.url(), {
        data: { search: searchQuery.value },
        preserveState: true,
        preserveScroll: true,
    });
};

watchDebounced(searchQuery, applyFilters, { debounce: 500, maxWait: 1000 });

// Drawer State
const isDrawerOpen = ref(false);
const editingItem = ref<any>(null);

const openCreate = () => {
    editingItem.value = null;
    isDrawerOpen.value = true;
};

const openEdit = (item: any) => {
    editingItem.value = item;
    isDrawerOpen.value = true;
};

// Delete Dialog State
const deleteDialogOpen = ref(false);
const itemToDelete = ref<any>(null);

const promptDelete = (item: any) => {
    itemToDelete.value = item;
    deleteDialogOpen.value = true;
};

const confirmDelete = () => {
    if (!itemToDelete.value) return;
    router.delete(destroy.url(itemToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            itemToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="<Models>" />

    <div class="space-y-6">
        <!-- Header & Action Button -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-foreground"><Models></h2>
                <p class="text-sm text-muted-foreground">Manage and monitor <models> across your workspace.</p>
            </div>
            <Button v-if="can('<slug>.create')" @click="openCreate" class="gap-2">
                <Plus class="size-4" />
                Add <Model>
            </Button>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="searchQuery"
                    placeholder="Search <models>..."
                    class="pl-9"
                />
            </div>
        </div>

        <!-- Data Table -->
        <div class="rounded-md border bg-card">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Created</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.data.length > 0">
                        <TableRow v-for="item in items.data" :key="item.id">
                            <TableCell class="font-medium">
                                <div>{{ item.name }}</div>
                                <div v-if="item.description" class="text-xs text-muted-foreground line-clamp-1">
                                    {{ item.description }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="item.is_active ? 'outline' : 'secondary'">
                                    {{ item.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                <RelativeTime :date="item.created_at" />
                            </TableCell>
                            <TableCell class="text-right">
                                <DropdownMenu v-if="can('<slug>.edit') || can('<slug>.delete')">
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="size-8">
                                            <MoreHorizontal class="size-4" />
                                            <span class="sr-only">Actions</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem v-if="can('<slug>.edit')" @click="openEdit(item)">
                                            <Pencil class="mr-2 size-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="can('<slug>.delete')"
                                            class="text-destructive focus:text-destructive"
                                            @click="promptDelete(item)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="4" class="h-32 text-center text-muted-foreground">
                            No <models> found matching your criteria.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="items.meta && items.meta.total > 0" class="flex items-center justify-between text-sm text-muted-foreground">
            <div>
                Showing {{ items.meta.from }} to {{ items.meta.to }} of {{ items.meta.total }} <models>
            </div>
        </div>

        <!-- Drawer Component (Strictly sized) -->
        <<Model>Drawer
            v-model:is-open="isDrawerOpen"
            :item="editingItem"
        />

        <!-- Delete Confirmation Modal -->
        <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently delete "{{ itemToDelete?.name }}". This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDelete">
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
```

---

#### 📄 Standard `<Model>Drawer.vue` Template (with Strict Drawer Sizing):

```vue
<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import InputError from '@/components/InputError.vue';
import { store, update } from '@/actions/App/Http/Controllers/Admin/<Model>Controller';

const props = defineProps<{
    isOpen: boolean;
    item: any | null;
}>();

const emit = defineEmits<{
    'update:isOpen': [value: boolean];
}>();

const form = useForm({
    name: '',
    description: '',
    is_active: true,
});

watch(
    () => props.item,
    (item) => {
        if (item) {
            form.name = item.name || '';
            form.description = item.description || '';
            form.is_active = item.is_active ?? true;
        } else {
            form.reset();
        }
        form.clearErrors();
    },
    { immediate: true },
);

const submit = () => {
    if (props.item) {
        form.put(update.url(props.item.id), {
            preserveScroll: true,
            onSuccess: () => emit('update:isOpen', false),
        });
    } else {
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                emit('update:isOpen', false);
            },
        });
    }
};
</script>

<template>
    <Drawer :open="isOpen" @update:open="emit('update:isOpen', $event)">
        <!-- STRICT DRAWER SIZING: md:inset-x-auto md:right-2 md:left-64 -->
        <DrawerContent class="max-h-[92vh] overflow-y-auto md:inset-x-auto md:right-2 md:left-64">
            <div class="w-full">
                <DrawerHeader class="border-b border-border/40 px-4 pt-3 pb-2">
                    <DrawerTitle>{{ item ? 'Edit <Model>' : 'Create <Model>' }}</DrawerTitle>
                    <DrawerDescription>
                        {{ item ? 'Update <model> attributes and settings.' : 'Fill out the details below to register a new <model>.' }}
                    </DrawerDescription>
                </DrawerHeader>

                <form @submit.prevent="submit" class="flex flex-col space-y-4 p-4">
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label for="name">Name <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Enter name..." required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Input id="description" v-model="form.description" placeholder="Optional notes..." />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div class="flex items-center justify-between rounded-lg border p-3">
                            <div class="space-y-0.5">
                                <Label for="is_active">Active Status</Label>
                                <p class="text-xs text-muted-foreground">Enable or disable this record across the system.</p>
                            </div>
                            <Switch id="is_active" :model-value="form.is_active" @update:model-value="form.is_active = $event" />
                        </div>
                    </div>

                    <DrawerFooter class="flex flex-row justify-end gap-2 px-0 pt-4">
                        <Button type="button" variant="outline" @click="emit('update:isOpen', false)">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : item ? 'Update' : 'Create' }}
                        </Button>
                    </DrawerFooter>
                </form>
            </div>
        </DrawerContent>
    </Drawer>
</template>
```

---

### Step 8: Sidebar Registration

Register the new module link in `resources/js/layouts/admin/AdminSidebar.vue`:

1. Import the Wayfinder route:
   ```ts
   import { index as index<Models> } from '@/routes/admin/<slug>';
   ```
2. In the `mainNavItems` computed property, push the item wrapped in the RBAC permission check:
   ```ts
   if (can('<slug>.view')) {
       items.push({
           title: '<Models>',
           href: index<Models>(),
           icon: <ChosenLucideIcon>,
       });
   }
   ```

---

### Step 9: Automated Pest Feature Tests

Scaffold the Pest test file via Artisan:
```bash
php artisan make:test Admin/<Model>Test --pest --no-interaction
```

Populate `tests/Feature/Admin/<Model>Test.php` with complete regression coverage:

```php
<?php

use App\Models\Admin;
use App\Models\<Model>;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->superAdmin = Admin::factory()->create();
    $this->superAdmin->assignRole('Super Admin');
});

it('allows super admin to view <slug> index', function () {
    <Model>::factory()->count(3)->create();

    $this->actingAs($this->superAdmin, 'admin')
        ->get(route('admin.<slug>.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/<slug>/Index')
            ->has('items.data', 3)
        );
});

it('prevents unauthorized admin from viewing <slug> index', function () {
    $guestAdmin = Admin::factory()->create(); // No roles

    $this->actingAs($guestAdmin, 'admin')
        ->get(route('admin.<slug>.index'))
        ->assertForbidden();
});

it('allows creating a <model> with valid data', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.<slug>.store'), [
            'name' => 'Test Item',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('<table_name>', [
        'name' => 'Test Item',
        'is_active' => true,
    ]);
});

it('validates required fields on create', function () {
    $this->actingAs($this->superAdmin, 'admin')
        ->post(route('admin.<slug>.store'), [])
        ->assertSessionHasErrors(['name']);
});

it('allows updating an existing <model>', function () {
    $item = <Model>::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->superAdmin, 'admin')
        ->put(route('admin.<slug>.update', $item), [
            'name' => 'New Name',
            'is_active' => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('<table_name>', [
        'id' => $item->id,
        'name' => 'New Name',
    ]);
});

it('allows deleting a <model>', function () {
    $item = <Model>::factory()->create();

    $this->actingAs($this->superAdmin, 'admin')
        ->delete(route('admin.<slug>.destroy', $item))
        ->assertRedirect();

    $this->assertDatabaseMissing('<table_name>', [
        'id' => $item->id,
    ]);
});
```

---

## 🏁 Post-Generation Verification Checklist

After generating the CRUD module, always run these commands sequentially before finalizing:

1. **Database Migration**:
   ```bash
   php artisan migrate --no-interaction
   ```
2. **Re-seed Permissions**:
   ```bash
   php artisan db:seed --class=RoleAndPermissionSeeder --no-interaction
   ```
3. **Regenerate Wayfinder Types**:
   ```bash
   php artisan wayfinder:generate --no-interaction
   ```
4. **Run Code Formatter (Laravel Pint)**:
   ```bash
   vendor/bin/pint --dirty --format agent
   ```
5. **Run Pest Tests**:
   ```bash
   php artisan test --compact --filter=<Model>
   ```
6. **Trigger Module QA Verification**:
   - Run `/admin-module-tester` for `<Models>` to verify UI, debounced search, drawer modals, and end-to-end browser operations via Chrome DevTools MCP.
