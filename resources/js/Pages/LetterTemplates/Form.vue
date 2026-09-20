<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import GrapesEditor from '@/Components/GrapesEditor.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    template: Object,      // null = create mode
    placeholders: Object,
    orderPlaceholders: Object,
    starterHtml: String,
});

const isEdit = computed(() => !!props.template);

const editorRef = ref(null);

const form = useForm({
    name: props.template?.name ?? '',
    type: props.template?.type ?? 'letter',
    html_content: props.template?.html_content ?? props.starterHtml ?? '',
});

const activePlaceholders = computed(() =>
    form.type === 'order' ? props.orderPlaceholders : props.placeholders
);

const typeLabel = computed(() => form.type === 'order' ? 'Order / Invoice Template' : 'Letter Template');

const submit = () => {
    // Pull the very latest HTML from GrapesJS at save time
    if (editorRef.value) {
        form.html_content = editorRef.value.getHtml();
    }
    if (isEdit.value) {
        form.put(route('letter-templates.update', props.template.id));
    } else {
        form.post(route('letter-templates.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Template' : 'New Template'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('letter-templates.index')" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Edit Template: ' + template.name : 'New Letter Template' }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Name -->
                    <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                        <div class="max-w-2xl grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="name" value="Template Name *" />
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="e.g. My Branded Letter"
                                />
                                <InputError class="mt-1" :message="form.errors.name" />
                            </div>
                            <div>
                                <InputLabel for="type" value="Template Type *" />
                                <select id="type" v-model="form.type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="letter">Letter Template</option>
                                    <option value="order">Order / Invoice Template</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-400">
                                    <span v-if="form.type === 'order'">Use <code>{{order_items_table}}</code>, <code>{{order_total}}</code>, etc.</span>
                                    <span v-else>Use <code>{{body}}</code>, <code>{{recipient_name}}</code>, etc.</span>
                                </p>
                                <InputError class="mt-1" :message="form.errors.type" />
                            </div>
                        </div>
                    </div>

                    <!-- Visual Drag & Drop Editor -->
                    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
                        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                            <span class="text-sm font-medium text-gray-700">Drag & Drop Editor</span>
                            <span class="text-xs text-gray-400">Drag blocks from the left panel onto the canvas</span>
                        </div>

                        <GrapesEditor
                            ref="editorRef"
                            v-model="form.html_content"
                            :placeholders="activePlaceholders"
                        />

                        <InputError class="px-4 pb-3" :message="form.errors.html_content" />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pb-4">
                        <Link :href="route('letter-templates.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Cancel
                        </Link>
                        <PrimaryButton type="submit" :disabled="form.processing" class="px-6">
                            {{ form.processing ? 'Saving...' : (isEdit ? 'Save Changes' : 'Create Template') }}
                        </PrimaryButton>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
