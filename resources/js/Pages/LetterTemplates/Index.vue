<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    systemTemplates: Array,
    customTemplates: Array,
    placeholders: Object,
    orderPlaceholders: Object,
    canManage: Boolean,
});

const placeholderTab = ref('letter');

const deleteTpl = (tpl) => {
    if (confirm(`Delete template "${tpl.name}"?`)) {
        router.delete(route('letter-templates.destroy', tpl.id));
    }
};

const duplicate = (tpl) => {
    router.post(route('letter-templates.duplicate', tpl.id));
};
</script>

<template>
    <Head title="Letter Templates" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('settings.index')" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Letter Templates</h2>
                </div>
                <Link v-if="canManage" :href="route('letter-templates.create')" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition">
                    + New Template
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-8">

                <!-- System Templates -->
                <div>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Built-in Templates</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div
                            v-for="tpl in systemTemplates"
                            :key="tpl.id"
                            class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-200"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ tpl.name }}</p>
                                    <span class="mt-1 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">System</span>
                                </div>
                                <div
                                    class="h-10 w-10 rounded-lg flex items-center justify-center"
                                    :class="{
                                        'bg-blue-50': tpl.base_design === 'formal',
                                        'bg-violet-50': tpl.base_design === 'modern',
                                        'bg-gray-50': tpl.base_design === 'classic',
                                    }"
                                >
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">Read-only built-in template. Duplicate to customise.</p>
                            <div v-if="canManage" class="mt-4">
                                <button @click="duplicate(tpl)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                    Duplicate &amp; Edit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Templates -->
                <div>
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Your Templates</h3>

                    <div v-if="!customTemplates.length" class="rounded-xl bg-white p-10 text-center shadow-sm ring-1 ring-gray-200">
                        <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <p class="text-sm text-gray-400">No custom templates yet. Create one or duplicate a built-in.</p>
                    </div>

                    <div v-else class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 divide-y divide-gray-100">
                        <div
                            v-for="tpl in customTemplates"
                            :key="tpl.id"
                            class="flex items-center justify-between px-6 py-4"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-gray-900">{{ tpl.name }}</p>
                                    <span :class="tpl.type === 'order' ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700'"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                                        {{ tpl.type === 'order' ? 'Order' : 'Letter' }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-0.5">Last updated {{ new Date(tpl.updated_at).toLocaleDateString() }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button v-if="canManage" @click="duplicate(tpl)" class="text-sm text-gray-500 hover:text-gray-700">Duplicate</button>
                                <Link v-if="canManage" :href="route('letter-templates.edit', tpl.id)" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Edit</Link>
                                <button v-if="canManage" @click="deleteTpl(tpl)" class="text-sm font-medium text-red-500 hover:text-red-600">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Placeholder Reference -->
                <div class="rounded-xl bg-gray-50 p-6 ring-1 ring-gray-200">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Available Placeholders</h3>
                        <div class="flex gap-1 rounded-lg bg-gray-200 p-1">
                            <button @click="placeholderTab = 'letter'" :class="placeholderTab === 'letter' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                                class="rounded-md px-3 py-1 text-xs font-medium transition">Letter</button>
                            <button @click="placeholderTab = 'order'" :class="placeholderTab === 'order' ? 'bg-white shadow text-gray-800' : 'text-gray-500 hover:text-gray-700'"
                                class="rounded-md px-3 py-1 text-xs font-medium transition">Order / Invoice</button>
                        </div>
                    </div>
                    <p class="mb-4 text-xs text-gray-500">Use these in your template HTML. They are replaced with real values when generating the PDF.</p>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div v-for="(desc, key) in (placeholderTab === 'order' ? orderPlaceholders : placeholders)" :key="key" class="flex items-baseline gap-2">
                            <code class="rounded bg-indigo-50 px-1.5 py-0.5 text-xs font-mono text-indigo-700">{{ key }}</code>
                            <span class="text-xs text-gray-500">{{ desc }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
