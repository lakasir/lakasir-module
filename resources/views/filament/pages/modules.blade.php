<x-filament-panels::page>
  @php($modules = $this->getModules())

  <div class="mb-6 flex gap-2">
    @foreach (['installed' => __('Installed'), 'catalog' => __('Catalog'), 'upload' => __('Upload ZIP')] as $key => $label)
      <x-filament::button
        wire:click="setTab('{{ $key }}')"
        :color="$tab === $key ? 'primary' : 'gray'"
        :outlined="$tab !== $key"
        size="sm">
        {{ $label }}
      </x-filament::button>
    @endforeach
  </div>

  @if ($tab === 'installed')
    @if (empty($modules))
      <x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No module installed yet.') }}</p>
      </x-filament::section>
    @else
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($modules as $module)
          <x-filament::section>
            <x-slot name="heading">
              <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-puzzle-piece" class="h-5 w-5" />
                {{ $module['title'] }}
              </div>
            </x-slot>

            <x-slot name="description">{{ $module['package'] }}</x-slot>

            <dl class="space-y-1 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Version') }}</dt>
                <dd>{{ $module['version'] }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Source') }}</dt>
                <dd>
                  @switch($module['source'])
                    @case('git')
                      <x-filament::badge color="info">{{ __('Git checkout') }}</x-filament::badge>
                      @break
                    @case('registry')
                      <x-filament::badge color="primary">{{ __('Registry') }}</x-filament::badge>
                      @break
                    @case('upload')
                      <x-filament::badge color="warning">{{ __('Uploaded ZIP') }}</x-filament::badge>
                      @break
                    @default
                      <x-filament::badge color="gray">{{ __('Local') }}</x-filament::badge>
                  @endswitch
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Migrations') }}</dt>
                <dd>
                  @if ($module['migrated'])
                    <x-filament::badge color="success">{{ __('Migrated') }}</x-filament::badge>
                  @else
                    <x-filament::badge color="danger">{{ __('Not migrated') }}</x-filament::badge>
                  @endif
                </dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Filament resources') }}</dt>
                <dd>
                  @if ($module['has_filament'])
                    <x-filament::badge color="success">{{ __('Available') }}</x-filament::badge>
                  @else
                    <x-filament::badge color="gray">{{ __('None') }}</x-filament::badge>
                  @endif
                </dd>
              </div>
            </dl>

            @if ($module['removable'])
              <div class="mt-4">
                <x-filament::button
                  color="danger"
                  size="xs"
                  outlined
                  wire:click="uninstall({{ $module['plugin_id'] }})"
                  wire:confirm="{{ __('Remove this module and its files?') }}">
                  {{ __('Remove') }}
                </x-filament::button>
              </div>
            @endif
          </x-filament::section>
        @endforeach
      </div>
    @endif

    @php($removed = $this->getRemovedPlugins())

    @if (! empty($removed))
      <div class="mt-8">
        <x-filament::section>
          <x-slot name="heading">{{ __('Removed, data kept') }}</x-slot>
          <x-slot name="description">
            {{ __('These modules are no longer installed. Their database tables and settings are still here so a reinstall picks up where it left off. Deleting the data is permanent.') }}
          </x-slot>

          <div class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($removed as $entry)
              <div class="flex items-center justify-between gap-4 py-3">
                <div class="text-sm">
                  <span class="font-mono">{{ $entry['name'] }}</span>
                  <span class="ms-2 text-gray-500 dark:text-gray-400">
                    {{ $entry['version'] }} · {{ $entry['removed_at']->diffForHumans() }}
                  </span>
                </div>
                <x-filament::button
                  color="danger"
                  size="xs"
                  outlined
                  wire:click="purge({{ $entry['plugin_id'] }})"
                  wire:confirm="{{ __('Delete this module\'s data permanently? This cannot be undone.') }}">
                  {{ __('Delete data') }}
                </x-filament::button>
              </div>
            @endforeach
          </div>
        </x-filament::section>
      </div>
    @endif
  @endif

  @if ($tab === 'catalog')
    @if ($catalogError)
      <x-filament::section>
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $catalogError }}</p>
        <div class="mt-3">
          <x-filament::button size="sm" wire:click="loadCatalog">{{ __('Retry') }}</x-filament::button>
        </div>
      </x-filament::section>
    @elseif (! $this->catalogLoaded())
      <x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          <span wire:loading wire:target="loadCatalog">{{ __('Loading the module catalog…') }}</span>
          <span wire:loading.remove wire:target="loadCatalog">{{ __('The catalog has not been loaded yet.') }}</span>
        </p>
        <div class="mt-3" wire:loading.remove wire:target="loadCatalog">
          <x-filament::button size="sm" wire:click="loadCatalog">{{ __('Load catalog') }}</x-filament::button>
        </div>
      </x-filament::section>
    @else
      @php($catalog = $this->getCatalog())

      <div class="mb-3 flex justify-end">
        <x-filament::button size="xs" color="gray" outlined wire:click="loadCatalog"
                            wire:loading.attr="disabled" wire:target="loadCatalog">
          {{ __('Refresh') }}
        </x-filament::button>
      </div>

      @if (empty($catalog))
        <x-filament::section>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Every module in the registry is already installed.') }}
          </p>
        </x-filament::section>
      @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          @foreach ($catalog as $module)
            <x-filament::section>
              <x-slot name="heading">{{ $module['title'] ?? $module['name'] }}</x-slot>
              <x-slot name="description">{{ $module['name'] }}</x-slot>

              <p class="text-sm text-gray-500 dark:text-gray-400">{{ $module['description'] ?? '' }}</p>

              <dl class="mt-3 space-y-1 text-sm">
                <div class="flex justify-between gap-4">
                  <dt class="text-gray-500 dark:text-gray-400">{{ __('Author') }}</dt>
                  <dd>{{ $module['author'] ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                  <dt class="text-gray-500 dark:text-gray-400">{{ __('Version') }}</dt>
                  <dd>{{ $module['version'] ?? '-' }}</dd>
                </div>
              </dl>

              <div class="mt-4">
                <x-filament::button
                  size="sm"
                  wire:click="installFromRegistry('{{ $module['name'] }}')"
                  wire:loading.attr="disabled">
                  {{ __('Install') }}
                </x-filament::button>
              </div>
            </x-filament::section>
          @endforeach
        </div>
      @endif
    @endif
  @endif

  @if ($tab === 'upload')
    <x-filament::section>
      <x-slot name="heading">{{ __('Install from a release archive') }}</x-slot>
      <x-slot name="description">
        {{ __('Upload a module .zip built for release. Git checkouts are never replaced.') }}
      </x-slot>

      <div class="space-y-4">
        <input type="file" wire:model="archive" accept=".zip"
               class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white" />

        <div wire:loading wire:target="archive" class="text-sm text-gray-500">
          {{ __('Uploading…') }}
        </div>

        @error('archive')
          <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror

        @if ($preview = $this->inspectArchive())
          @if (isset($preview['error']))
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $preview['error'] }}</p>
          @else
            <dl class="space-y-1 text-sm">
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Package') }}</dt>
                <dd class="font-mono">{{ $preview['name'] }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Installs to') }}</dt>
                <dd class="font-mono">modules/{{ $preview['folder'] }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Version') }}</dt>
                <dd>{{ $preview['version'] ?? '-' }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Author') }}</dt>
                <dd>{{ $preview['author'] ?? '-' }}</dd>
              </div>
              <div class="flex justify-between gap-4">
                <dt class="text-gray-500 dark:text-gray-400">{{ __('Contents') }}</dt>
                <dd>{{ trans_choice('{1} :count file|[2,*] :count files', $preview['files'], ['count' => $preview['files']]) }}
                  · {{ number_format($preview['size'] / 1024, 1) }} KB</dd>
              </div>
            </dl>

            @if ($preview['conflict'])
              <p class="text-sm text-warning-600 dark:text-warning-400">
                {{ __('modules/:folder already exists and will be replaced.', ['folder' => $preview['folder']]) }}
              </p>
            @endif

            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ __('Installing runs the module\'s composer install and its migrations on every tenant.') }}
            </p>

            <x-filament::button wire:click="installArchive" wire:loading.attr="disabled">
              {{ __('Install module') }}
            </x-filament::button>
          @endif
        @endif
      </div>
    </x-filament::section>
  @endif
</x-filament-panels::page>
