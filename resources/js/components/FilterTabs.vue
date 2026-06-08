<script setup>
defineProps({
    tabs: { type: Array, required: true }, // [{ id, label, icon? }]
    modelValue: { type: String, required: true },
    counts: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="pp-filtertabs" role="tablist">
        <button
            v-for="t in tabs"
            :key="t.id"
            role="tab"
            type="button"
            :aria-selected="modelValue === t.id"
            class="ft"
            :class="{ on: modelValue === t.id }"
            @click="emit('update:modelValue', t.id)"
        >
            <component :is="t.icon" v-if="t.icon" :size="14" />
            {{ t.label }}
            <span v-if="counts && counts[t.id] != null" class="cnt">{{
                counts[t.id]
            }}</span>
        </button>
    </div>
</template>
