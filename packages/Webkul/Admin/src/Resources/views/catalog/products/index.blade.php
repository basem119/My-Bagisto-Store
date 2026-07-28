<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.catalog.products.index.title')
    </x-slot>

    <div class="flex items-center justify-between gap-4 max-sm:flex-wrap">
        <p class="text-xl font-bold text-gray-800 dark:text-white">
            @lang('admin::app.catalog.products.index.title')
        </p>

        <div class="flex items-center gap-x-2.5">
            <!-- Export Modal -->
            <x-admin::datagrid.export :src="route('admin.catalog.products.index')" />

            <!-- Import Products -->
            <v-import-products></v-import-products>

            {!! view_render_event('bagisto.admin.catalog.products.create.before') !!}

            @if (bouncer()->hasPermission('catalog.products.create'))
                <v-create-product-form>
                    <button
                        type="button"
                        class="primary-button"
                    >
                        @lang('admin::app.catalog.products.index.create-btn')
                    </button>
                </v-create-product-form>
            @endif

            {!! view_render_event('bagisto.admin.catalog.products.create.after') !!}
        </div>
    </div>

    {!! view_render_event('bagisto.admin.catalog.products.list.before') !!}

    <!-- Datagrid -->
    <x-admin::datagrid
        :src="route('admin.catalog.products.index')"
        :isMultiRow="true"
    >
        <!-- Datagrid Header -->
        @php
            $hasPermission = bouncer()->hasPermission('catalog.products.edit') || bouncer()->hasPermission('catalog.products.delete');
        @endphp

        <template #header="{
            isLoading,
            available,
            applied,
            selectAll,
            sort,
            performAction
        }">
            <template v-if="isLoading">
                <x-admin::shimmer.datagrid.table.head :isMultiRow="true" />
            </template>

            <template v-else>
                <div class="row grid gap-2 md:grid-cols-[2fr_1fr_1fr] grid-rows-1 items-center border-b px-4 py-2.5 dark:border-gray-800">
                    <div
                        class="flex select-none items-center gap-2.5"
                        v-for="(columnGroup, index) in [['name', 'sku', 'attribute_family'], ['base_image', 'price', 'quantity', 'product_id'], ['status', 'category_name', 'type']]"
                    >
                        @if ($hasPermission)
                            <label
                                class="flex w-max cursor-pointer select-none items-center gap-1"
                                for="mass_action_select_all_records"
                                v-if="! index"
                            >
                                <input
                                    type="checkbox"
                                    name="mass_action_select_all_records"
                                    id="mass_action_select_all_records"
                                    class="peer hidden"
                                    :checked="['all', 'partial'].includes(applied.massActions.meta.mode)"
                                    @change="selectAll"
                                >

                                <span
                                    class="icon-uncheckbox cursor-pointer rounded-md text-2xl"
                                    :class="[
                                        applied.massActions.meta.mode === 'all' ? 'peer-checked:icon-checked peer-checked:text-blue-600' : (
                                            applied.massActions.meta.mode === 'partial' ? 'peer-checked:icon-checkbox-partial peer-checked:text-blue-600' : ''
                                        ),
                                    ]"
                                >
                                </span>
                            </label>
                        @endif

                        <p class="text-gray-600 dark:text-gray-300">
                            <span class="[&>*]:after:content-['_/_']">
                                <template v-for="column in columnGroup">
                                    <span
                                        class="after:content-['/'] last:after:content-['']"
                                        :class="{
                                            'font-medium text-gray-800 dark:text-white': applied.sort.column == column,
                                            'cursor-pointer hover:text-gray-800 dark:hover:text-white': available.columns.find(columnTemp => columnTemp.index === column)?.sortable,
                                        }"
                                        @click="
                                            available.columns.find(columnTemp => columnTemp.index === column)?.sortable ? sort(available.columns.find(columnTemp => columnTemp.index === column)): {}
                                        "
                                    >
                                        @{{ available.columns.find(columnTemp => columnTemp.index === column)?.label }}
                                    </span>
                                </template>
                            </span>

                            <i
                                class="align-text-bottom text-base text-gray-800 dark:text-white ltr:ml-1.5 rtl:mr-1.5"
                                :class="[applied.sort.order === 'asc' ? 'icon-down-stat': 'icon-up-stat']"
                                v-if="columnGroup.includes(applied.sort.column)"
                            ></i>
                        </p>
                    </div>
                </div>
            </template>
        </template>

        <template #body="{
            isLoading,
            available,
            applied,
            selectAll,
            sort,
            performAction
        }">
            <template v-if="isLoading">
                <x-admin::shimmer.datagrid.table.body :isMultiRow="true" />
            </template>

            <template v-else>
                <div
                    class="row border-b px-2 py-2.5 transition-all hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950 sm:px-4 md:grid md:grid-cols-[2fr_1fr_1fr] md:grid-rows-1 md:gap-1.5"
                    v-for="record in available.records"
                >
                    <!-- Mobile Layout -->
                    <div class="block space-y-3 md:hidden">
                        <!-- Header Row with Checkbox, Name and Actions -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2.5">
                                @if ($hasPermission)
                                    <input
                                        type="checkbox"
                                        :name="`mass_action_select_record_${record.product_id}`"
                                        :id="`mass_action_select_record_${record.product_id}`"
                                        :value="record.product_id"
                                        class="peer hidden"
                                        v-model="applied.massActions.indices"
                                    >

                                    <label
                                        class="icon-uncheckbox peer-checked:icon-checked cursor-pointer rounded-md text-2xl peer-checked:text-blue-600"
                                        :for="`mass_action_select_record_${record.product_id}`"
                                    ></label>
                                @endif

                                <div class="relative flex-shrink-0">
                                    <template v-if="record.base_image">
                                        <img
                                            class="h-12 w-12 rounded object-cover sm:h-16 sm:w-16"
                                            :src='record.base_image'
                                        />

                                        <span class="absolute -bottom-1 -right-1 rounded-full bg-darkPink px-1 text-xs font-bold leading-normal text-white">
                                            @{{ record.images_count }}
                                        </span>
                                    </template>

                                    <template v-else>
                                        <div class="relative h-12 w-12 rounded border border-dashed border-gray-300 dark:border-gray-800 dark:mix-blend-exclusion dark:invert sm:h-16 sm:w-16">
                                            <img src="{{ bagisto_asset('images/product-placeholders/front.svg')}}" class="h-full w-full object-cover">

                                            <p class="absolute bottom-0 w-full text-center text-[6px] font-semibold text-gray-400">
                                                @lang('admin::app.catalog.products.index.datagrid.product-image')
                                            </p>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex flex-col gap-1 flex-1">
                                    <p class="break-all text-sm font-semibold text-gray-800 dark:text-white sm:text-base">
                                        @{{ record.name }}
                                    </p>

                                    <p class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                        @{{ "@lang('admin::app.catalog.products.index.datagrid.id-value')".replace(':id', record.product_id) }}
                                    </p>

                                    <p class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                        @{{ "@lang('admin::app.catalog.products.index.datagrid.sku-value')".replace(':sku', record.sku) }}
                                    </p>

                                    <p class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                        @{{ "@lang('admin::app.catalog.products.index.datagrid.attribute-family-value')".replace(':attribute_family', record.attribute_family) }}
                                    </p>

                                    <p class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                        @{{ record.category_name ?? 'N/A' }}
                                    </p>

                                    <p class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                        @{{ record.type }}
                                    </p>

                                    <p class="text-sm font-semibold text-gray-800 dark:text-white sm:text-base">
                                        @{{ $admin.formatPrice(record.price) }}
                                    </p>

                                    <div>
                                        <div v-if="['configurable', 'bundle', 'grouped' , 'booking'].includes(record.type)">
                                            <p class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                                <span class="text-red-600">N/A</span>
                                            </p>
                                        </div>

                                        <div v-else>
                                            <p
                                                class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm"
                                                v-if="record.quantity > 0"
                                            >
                                                <span class="text-green-600">
                                                    @{{ "@lang('admin::app.catalog.products.index.datagrid.qty-value')".replace(':qty', record.quantity) }}
                                                </span>
                                            </p>

                                            <p
                                                class="text-xs text-gray-600 dark:text-gray-300 sm:text-sm"
                                                v-else
                                            >
                                                <span class="text-red-600">
                                                    @lang('admin::app.catalog.products.index.datagrid.out-of-stock')
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <p :class="[record.status ? 'label-active': 'label-info']">
                                        @{{ record.status ? "@lang('admin::app.catalog.products.index.datagrid.active')" : "@lang('admin::app.catalog.products.index.datagrid.disable')" }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1">
                                <span
                                    class="cursor-pointer rounded-md p-1.5 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800"
                                    :class="action.icon"
                                    v-text="! action.icon ? action.title : ''"
                                    v-for="action in record.actions"
                                    @click="performAction(action)"
                                >
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Layout (Hidden on Mobile) -->
                    <div class="hidden md:contents">
                        <!-- Name, SKU, Attribute Family Columns -->
                        <div class="flex gap-2.5">
                            @if ($hasPermission)
                                <input
                                    type="checkbox"
                                    :name="`mass_action_select_record_${record.product_id}`"
                                    :id="`mass_action_select_record_${record.product_id}`"
                                    :value="record.product_id"
                                    class="peer hidden"
                                    v-model="applied.massActions.indices"
                                >

                                <label
                                    class="icon-uncheckbox peer-checked:icon-checked cursor-pointer rounded-md text-2xl peer-checked:text-blue-600"
                                    :for="`mass_action_select_record_${record.product_id}`"
                                ></label>
                            @endif

                            <div class="flex flex-col gap-1.5">
                                <p class="break-all text-base font-semibold text-gray-800 dark:text-white">
                                    @{{ record.name }}
                                </p>

                                <p class="text-gray-600 dark:text-gray-300">
                                    @{{ "@lang('admin::app.catalog.products.index.datagrid.sku-value')".replace(':sku', record.sku) }}
                                </p>

                                <p class="text-gray-600 dark:text-gray-300">
                                    @{{ "@lang('admin::app.catalog.products.index.datagrid.attribute-family-value')".replace(':attribute_family', record.attribute_family) }}
                                </p>
                            </div>
                        </div>

                        <!-- Image, Price, Id, Stock Columns -->
                        <div class="flex gap-1.5">
                            <div class="relative">
                                <template v-if="record.base_image">
                                    <img
                                        class="max-h-[65px] min-h-[65px] min-w-[65px] max-w-[65px] rounded"
                                        :src='record.base_image'
                                    />

                                    <span class="absolute bottom-px rounded-full bg-darkPink px-1.5 text-xs font-bold leading-normal text-white ltr:left-px rtl:right-px">
                                        @{{ record.images_count }}
                                    </span>
                                </template>

                                <template v-else>
                                    <div class="relative h-[60px] max-h-[60px] w-full max-w-[60px] rounded border border-dashed border-gray-300 dark:border-gray-800 dark:mix-blend-exclusion dark:invert">
                                        <img src="{{ bagisto_asset('images/product-placeholders/front.svg')}}">

                                        <p class="absolute bottom-1.5 w-full text-center text-[6px] font-semibold text-gray-400">
                                            @lang('admin::app.catalog.products.index.datagrid.product-image')
                                        </p>
                                    </div>
                                </template>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <p class="text-base font-semibold text-gray-800 dark:text-white">
                                    @{{ $admin.formatPrice(record.price) }}
                                </p>

                                <!-- Parent Product Quantity -->
                                <div v-if="['configurable', 'bundle', 'grouped' , 'booking'].includes(record.type)">
                                    <p class="text-gray-600 dark:text-gray-300">
                                        <span class="text-red-600">N/A</span>
                                    </p>
                                </div>

                                <div v-else>
                                    <p
                                        class="text-gray-600 dark:text-gray-300"
                                        v-if="record.quantity > 0"
                                    >
                                        <span class="text-green-600">
                                            @{{ "@lang('admin::app.catalog.products.index.datagrid.qty-value')".replace(':qty', record.quantity) }}
                                        </span>
                                    </p>

                                    <p
                                        class="text-gray-600 dark:text-gray-300"
                                        v-else
                                    >
                                        <span class="text-red-600">
                                            @lang('admin::app.catalog.products.index.datagrid.out-of-stock')
                                        </span>
                                    </p>
                                </div>

                                <p class="text-gray-600 dark:text-gray-300">
                                    @{{ "@lang('admin::app.catalog.products.index.datagrid.id-value')".replace(':id', record.product_id) }}
                                </p>
                            </div>
                        </div>

                        <!-- Status, Category, Type Columns -->
                        <div class="flex items-center justify-between gap-x-4">
                            <div class="flex flex-col gap-1.5">
                                <p :class="[record.status ? 'label-active': 'label-info']">
                                    @{{ record.status ? "@lang('admin::app.catalog.products.index.datagrid.active')" : "@lang('admin::app.catalog.products.index.datagrid.disable')" }}
                                </p>

                                <p class="text-gray-600 dark:text-gray-300">
                                    @{{ record.category_name ?? 'N/A' }}
                                </p>

                                <p class="text-gray-600 dark:text-gray-300">
                                    @{{ record.type }}
                                </p>
                            </div>

                            <p
                                class="flex items-center gap-1.5"
                                v-if="available.actions.length"
                            >
                                <span
                                    class="cursor-pointer rounded-md p-1.5 text-2xl transition-all hover:bg-gray-200 dark:hover:bg-gray-800 max-sm:place-self-center"
                                    :class="action.icon"
                                    v-text="! action.icon ? action.title : ''"
                                    v-for="action in record.actions"
                                    @click="performAction(action)"
                                >
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </template>
    </x-admin::datagrid>

    {!! view_render_event('bagisto.admin.catalog.products.list.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-create-product-form-template"
        >
            <div>
                <!-- Product Create Button -->
                @if (bouncer()->hasPermission('catalog.products.create'))
                    <button
                        type="button"
                        class="primary-button"
                        @click="$refs.productCreateModal.toggle()"
                    >
                        @lang('admin::app.catalog.products.index.create-btn')
                    </button>
                @endif

                <x-admin::form
                    v-slot="{ meta, errors, handleSubmit }"
                    as="div"
                >
                    <form @submit="handleSubmit($event, create)">
                        <!-- Customer Create Modal -->
                        <x-admin::modal ref="productCreateModal">
                            <!-- Modal Header -->
                            <x-slot:header>
                                <p
                                    class="text-lg font-bold text-gray-800 dark:text-white"
                                    v-if="! attributes.length"
                                >
                                    @lang('admin::app.catalog.products.index.create.title')
                                </p>

                                <p
                                    class="text-lg font-bold text-gray-800 dark:text-white"
                                    v-else
                                >
                                    @lang('admin::app.catalog.products.index.create.configurable-attributes')
                                </p>
                            </x-slot>

                            <!-- Modal Content -->
                            <x-slot:content>
                                <div v-show="! attributes.length">
                                    {!! view_render_event('bagisto.admin.catalog.products.create_form.general.controls.before') !!}

                                    <!-- Product Type -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.type')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="type"
                                            rules="required"
                                            :label="trans('admin::app.catalog.products.index.create.type')"
                                        >
                                            @foreach(config('product_types') as $key => $type)
                                                <option value="{{ $key }}">
                                                    @lang($type['name'])
                                                </option>
                                            @endforeach
                                        </x-admin::form.control-group.control>

                                        <x-admin::form.control-group.error control-name="type" />
                                    </x-admin::form.control-group>

                                    <!-- Attribute Family Id -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.family')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="attribute_family_id"
                                            rules="required"
                                            :label="trans('admin::app.catalog.products.index.create.family')"
                                        >
                                            @foreach($families as $family)
                                                <option value="{{ $family->id }}">
                                                    {{ $family->name }}
                                                </option>
                                            @endforeach
                                        </x-admin::form.control-group.control>

                                        <x-admin::form.control-group.error control-name="attribute_family_id" />
                                    </x-admin::form.control-group>

                                    <!-- SKU -->
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.catalog.products.index.create.sku')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="text"
                                            name="sku"
                                            ::rules="{ required: true, regex: /^[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*$/ }"
                                            :label="trans('admin::app.catalog.products.index.create.sku')"
                                        />

                                        <x-admin::form.control-group.error control-name="sku" />
                                    </x-admin::form.control-group>

                                    {!! view_render_event('bagisto.admin.catalog.products.create_form.general.controls.after') !!}
                                </div>

                                <div v-show="attributes.length">
                                    {!! view_render_event('bagisto.admin.catalog.products.create_form.attributes.controls.before') !!}

                                    <div
                                        class="mb-2.5"
                                        v-for="attribute in attributes"
                                    >
                                        <label
                                            class="block text-xs font-medium leading-6 text-gray-800 dark:text-white"
                                            v-text="attribute.name"
                                        >
                                        </label>

                                        <div class="flex min-h-[38px] flex-wrap gap-1 rounded-md border p-1.5 dark:border-gray-800">
                                            <p
                                                class="flex items-center rounded bg-gray-600 px-2 py-1 font-semibold text-white"
                                                v-for="option in attribute.options"
                                            >
                                                @{{ option.name }}

                                                <span
                                                    class="icon-cross cursor-pointer text-lg text-white ltr:ml-1.5 rtl:mr-1.5"
                                                    @click="removeOption(option)"
                                                >
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    {!! view_render_event('bagisto.admin.catalog.products.create_form.attributes.controls.after') !!}
                                </div>
                            </x-slot>

                            <!-- Modal Footer -->
                            <x-slot:footer>
                                <div class="flex items-center gap-x-2.5">
                                    <!-- Back Button -->
                                    <x-admin::button
                                        button-type="button"
                                        class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                                        :title="trans('admin::app.catalog.products.index.create.back-btn')"
                                        v-if="attributes.length"
                                        @click="attributes = []"
                                    />

                                    <!-- Save Button -->
                                    <x-admin::button
                                        button-type="button"
                                        class="primary-button"
                                        :title="trans('admin::app.catalog.products.index.create.save-btn')"
                                        ::loading="isLoading"
                                        ::disabled="isLoading"
                                    />
                                </div>
                            </x-slot>
                        </x-admin::modal>
                    </form>
                </x-admin::form>
            </div>
        </script>

        <script type="module">
            app.component('v-create-product-form', {
                template: '#v-create-product-form-template',

                data() {
                    return {
                        attributes: [],

                        superAttributes: {},

                        isLoading: false,
                    };
                },

                methods: {
                    create(params, { resetForm, resetField, setErrors }) {
                        this.isLoading = true;

                        this.attributes.forEach(attribute => {
                            params.super_attributes ||= {};

                            params.super_attributes[attribute.code] = this.superAttributes[attribute.code];
                        });

                        this.$axios.post("{{ route('admin.catalog.products.store') }}", params)
                            .then((response) => {
                                this.isLoading = false;

                                if (response.data.data.redirect_url) {
                                    window.location.href = response.data.data.redirect_url;
                                } else {
                                    this.attributes = response.data.data.attributes;

                                    this.setSuperAttributes();
                                }
                            })
                            .catch(error => {
                                this.isLoading = false;

                                if (error.response.status == 422) {
                                    setErrors(error.response.data.errors);
                                }
                            });
                    },

                    removeOption(option) {
                        this.attributes.forEach(attribute => {
                            attribute.options = attribute.options.filter(item => item.id != option.id);
                        });

                        this.attributes = this.attributes.filter(attribute => attribute.options.length > 0);

                        this.setSuperAttributes();
                    },

                    setSuperAttributes() {
                        this.superAttributes = {};

                        this.attributes.forEach(attribute => {
                            this.superAttributes[attribute.code] = [];

                            attribute.options.forEach(option => {
                                this.superAttributes[attribute.code].push(option.id);
                            });
                        });
                    }
                }
            })
        </script>

        <!-- Import Products Template -->
        <script type="text/x-template" id="v-import-products-template">
            <div>
                <!-- Import Button -->
                <button
                    type="button"
                    class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                    @click="openModal"
                >
                    <span class="icon-admin-store text-xl text-gray-600"></span>
                    Import Products
                </button>

                <!-- Import Modal -->
                <x-admin::modal ref="importModal">
                    <x-slot:header>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">
                            Import Products
                        </p>
                    </x-slot>

                    <x-slot:content>
                        <div v-if="step === 'form'">
                            <!-- Source Selection -->
                            <div class="mb-4">
                                <label class="mb-2 block text-xs font-medium text-gray-800 dark:text-white">
                                    Import Source
                                </label>

                                <div class="flex gap-4">
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="radio" v-model="source" value="upload" class="text-blue-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Upload ZIP</span>
                                    </label>

                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="radio" v-model="source" value="folder" class="text-blue-600">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Server Folder</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Upload ZIP -->
                            <div v-if="source === 'upload'" class="mb-4">
                                <label class="mb-2 block text-xs font-medium text-gray-800 dark:text-white">
                                    Upload ZIP File
                                </label>

                                <input
                                    type="file"
                                    ref="zipFile"
                                    accept=".zip"
                                    @change="onFileSelected"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >

                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Expected structure inside ZIP:
                                </p>
                                <pre class="mt-1 rounded bg-gray-100 p-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">products.csv
images/
  ├── PARENT-SKU/
  │   ├── Color/
  │   │   ├── 1.jpg
  │   │   └── 2.jpg</pre>
                            </div>

                            <!-- Server Folder -->
                            <div v-if="source === 'folder'" class="mb-4">
                                <label class="mb-2 block text-xs font-medium text-gray-800 dark:text-white">
                                    Select Folder
                                </label>

                                <div v-if="loadingFolders" class="py-2 text-sm text-gray-500">
                                    Loading folders...
                                </div>

                                <select
                                    v-else
                                    v-model="selectedFolder"
                                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    <option value="">-- Select folder --</option>
                                    <option v-for="f in folders" :key="f" :value="f">
                                        @{{ f }}
                                    </option>
                                </select>

                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    Folders in <code>storage/app/imports/</code>
                                </p>
                            </div>

                            <!-- Error Message -->
                            <div v-if="errorMessage" class="mt-2 rounded bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                @{{ errorMessage }}
                            </div>
                        </div>

                        <!-- Progress Screen -->
                        <div v-if="step === 'progress'" class="py-2">
                            <div class="mb-4">
                                <div class="mb-1 flex justify-between text-sm">
                                    <span class="text-gray-700 dark:text-gray-300">Importing...</span>
                                    <span class="font-medium text-gray-900 dark:text-white">@{{ progress.percentage }}%</span>
                                </div>
                                <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        class="h-3 rounded-full transition-all duration-500"
                                        :class="progress.status === 'failed' ? 'bg-red-500' : 'bg-blue-600'"
                                        :style="{ width: progress.percentage + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Created</p>
                                    <p class="text-lg font-bold text-green-600">@{{ progress.created_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Updated</p>
                                    <p class="text-lg font-bold text-blue-600">@{{ progress.updated_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Skipped</p>
                                    <p class="text-lg font-bold text-yellow-600">@{{ progress.skipped_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Images</p>
                                    <p class="text-lg font-bold text-purple-600">@{{ progress.image_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Errors</p>
                                    <p class="text-lg font-bold text-red-600">@{{ progress.error_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Duration</p>
                                    <p class="text-lg font-bold text-gray-700 dark:text-gray-300">@{{ progress.duration || '--:--:--' }}</p>
                                </div>
                            </div>

                            <div v-if="progress.status === 'failed'" class="mt-3 rounded bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400">
                                @{{ progress.error_message }}
                            </div>
                        </div>

                        <!-- Completion Screen -->
                        <div v-if="step === 'complete'" class="py-2">
                            <div class="mb-4 flex items-center gap-2">
                                <span class="text-2xl">&#x2705;</span>
                                <p class="text-lg font-bold text-green-600">Import Completed</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Products Created</p>
                                    <p class="text-xl font-bold text-green-600">@{{ progress.created_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Products Updated</p>
                                    <p class="text-xl font-bold text-blue-600">@{{ progress.updated_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Skipped</p>
                                    <p class="text-xl font-bold text-yellow-600">@{{ progress.skipped_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Images Imported</p>
                                    <p class="text-xl font-bold text-purple-600">@{{ progress.image_count }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Duration</p>
                                    <p class="text-xl font-bold text-gray-700 dark:text-gray-300">@{{ progress.duration }}</p>
                                </div>
                                <div class="rounded border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Errors</p>
                                    <p class="text-xl font-bold" :class="progress.error_count > 0 ? 'text-red-600' : 'text-green-600'">@{{ progress.error_count }}</p>
                                </div>
                            </div>
                        </div>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex items-center gap-x-2.5">
                            <!-- Form Step Buttons -->
                            <template v-if="step === 'form'">
                                <button
                                    type="button"
                                    class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                                    @click="$refs.importModal.toggle()"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    class="primary-button"
                                    :disabled="isStarting"
                                    @click="startImport"
                                >
                                    <span v-if="isStarting">Starting...</span>
                                    <span v-else>Start Import</span>
                                </button>
                            </template>

                            <!-- Completion Buttons -->
                            <template v-if="step === 'complete' || progress.status === 'failed'">
                                <a
                                    :href="logDownloadUrl"
                                    class="transparent-button hover:bg-gray-200 dark:text-white dark:hover:bg-gray-800"
                                    target="_blank"
                                >
                                    Download Log
                                </a>

                                <button
                                    type="button"
                                    class="primary-button"
                                    @click="closeAndRefresh"
                                >
                                    Close
                                </button>
                            </template>
                        </div>
                    </x-slot>
                </x-admin::modal>
            </div>
        </script>

        <script type="module">
            app.component('v-import-products', {
                template: '#v-import-products-template',

                data() {
                    return {
                        step: 'form',
                        source: 'upload',
                        selectedFile: null,
                        selectedFolder: '',
                        folders: [],
                        loadingFolders: false,
                        isStarting: false,
                        errorMessage: '',
                        batchId: null,
                        pollInterval: null,
                        progress: {
                            status: 'pending',
                            percentage: 0,
                            created_count: 0,
                            updated_count: 0,
                            skipped_count: 0,
                            image_count: 0,
                            error_count: 0,
                            duration: null,
                            error_message: null,
                        },
                    };
                },

                computed: {
                    logDownloadUrl() {
                        if (!this.batchId) return '#';
                        return `{{ route('admin.catalog.products.import.log', ':id') }}`.replace(':id', this.batchId);
                    }
                },

                watch: {
                    source(val) {
                        this.errorMessage = '';
                        if (val === 'folder') {
                            this.loadFolders();
                        }
                    }
                },

                methods: {
                    openModal() {
                        this.resetState();
                        this.$refs.importModal.toggle();
                    },

                    resetState() {
                        this.step = 'form';
                        this.source = 'upload';
                        this.selectedFile = null;
                        this.selectedFolder = '';
                        this.errorMessage = '';
                        this.isStarting = false;
                        this.batchId = null;
                        this.stopPolling();
                        this.progress = {
                            status: 'pending',
                            percentage: 0,
                            created_count: 0,
                            updated_count: 0,
                            skipped_count: 0,
                            image_count: 0,
                            error_count: 0,
                            duration: null,
                            error_message: null,
                        };
                    },

                    onFileSelected(e) {
                        this.selectedFile = e.target.files[0] || null;
                        this.errorMessage = '';
                    },

                    async loadFolders() {
                        this.loadingFolders = true;
                        try {
                            const response = await this.$axios.get("{{ route('admin.catalog.products.import.folders') }}");
                            this.folders = response.data.folders;
                        } catch (e) {
                            this.folders = [];
                        }
                        this.loadingFolders = false;
                    },

                    async startImport() {
                        this.errorMessage = '';

                        if (this.source === 'upload') {
                            if (!this.selectedFile) {
                                this.errorMessage = 'Please select a ZIP file.';
                                return;
                            }
                            await this.uploadZip();
                        } else {
                            if (!this.selectedFolder) {
                                this.errorMessage = 'Please select a folder.';
                                return;
                            }
                            await this.importFromFolder();
                        }
                    },

                    async uploadZip() {
                        this.isStarting = true;
                        const formData = new FormData();
                        formData.append('file', this.selectedFile);

                        try {
                            const response = await this.$axios.post(
                                "{{ route('admin.catalog.products.import.upload') }}",
                                formData,
                                { headers: { 'Content-Type': 'multipart/form-data' } }
                            );
                            this.batchId = response.data.batch_id;
                            this.step = 'progress';
                            this.startPolling();
                        } catch (e) {
                            this.errorMessage = this.extractError(e);
                        }
                        this.isStarting = false;
                    },

                    async importFromFolder() {
                        this.isStarting = true;
                        try {
                            const response = await this.$axios.post(
                                "{{ route('admin.catalog.products.import.folder') }}",
                                { folder: this.selectedFolder }
                            );
                            this.batchId = response.data.batch_id;
                            this.step = 'progress';
                            this.startPolling();
                        } catch (e) {
                            this.errorMessage = this.extractError(e);
                        }
                        this.isStarting = false;
                    },

                    extractError(e) {
                        if (!e.response) {
                            return 'Network error. Check that the server is running.';
                        }

                        const data = e.response.data;

                        // Laravel validation errors
                        if (data.errors) {
                            return Object.values(data.errors).flat().join(' ');
                        }

                        // Our custom error field
                        if (data.error) {
                            return data.error;
                        }

                        if (data.message) {
                            return data.message;
                        }

                        return `Upload failed (HTTP ${e.response.status})`;
                    },

                    startPolling() {
                        this.pollProgress();
                        this.pollInterval = setInterval(() => this.pollProgress(), 2000);
                    },

                    stopPolling() {
                        if (this.pollInterval) {
                            clearInterval(this.pollInterval);
                            this.pollInterval = null;
                        }
                    },

                    async pollProgress() {
                        if (!this.batchId) return;

                        try {
                            const url = `{{ route('admin.catalog.products.import.progress', ':id') }}`.replace(':id', this.batchId);
                            const response = await this.$axios.get(url);
                            this.progress = response.data;

                            if (this.progress.status === 'completed') {
                                this.stopPolling();
                                this.step = 'complete';
                            } else if (this.progress.status === 'failed') {
                                this.stopPolling();
                            }
                        } catch (e) {
                            // Keep polling on transient errors
                        }
                    },

                    closeAndRefresh() {
                        this.$refs.importModal.toggle();
                        window.location.reload();
                    }
                },

                beforeUnmount() {
                    this.stopPolling();
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
