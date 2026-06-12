<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <section class="pp-page">
        <header class="pp-pagehead">
            <div>
                <h1>{{ title }}</h1>
                <p v-if="subtitle" class="ph-sub">{{ subtitle }}</p>
            </div>
        </header>
    </section>
</template>
