<script setup>
import { IcStar } from '@/components/icons';

defineProps({
    active: { type: Boolean, default: false },
    size: { type: Number, default: 18 },
    label: { type: String, default: 'Follow' },
});

const emit = defineEmits(['toggle']);

const onClick = (e) => {
    e.stopPropagation();
    emit('toggle');
};
</script>

<template>
    <button
        class="pp-fav"
        :class="{ on: active }"
        type="button"
        :aria-pressed="active"
        :aria-label="label"
        :title="active ? 'Following' : 'Follow'"
        @click="onClick"
    >
        <IcStar :size="size" />
    </button>
</template>
