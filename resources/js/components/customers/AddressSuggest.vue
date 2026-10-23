<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
const props = withDefaults(defineProps<{ modelValue?: string; options: string[]; placeholder?: string }>(), { modelValue: "", placeholder: "Search or select" });
const emit = defineEmits<{ (e: "update:modelValue", value: string): void; (e: "add", value: string): void }>();
const open = ref(false), query = ref(props.modelValue), active = ref(0), root = ref<HTMLElement | null>(null);
watch(() => props.modelValue, (v) => { if (v !== query.value) query.value = v ?? ""; });
const filtered = computed(() => { const q=query.value.trim().toLocaleLowerCase(); return props.options.filter(v => !q || v.toLocaleLowerCase().includes(q)).slice(0, 100); });
const canAdd = computed(() => !!query.value.trim() && !props.options.some(v => v.toLocaleLowerCase() === query.value.trim().toLocaleLowerCase()));
function select(value: string) { query.value=value; emit("update:modelValue", value); open.value=false; active.value=0; }
function addNew() { const value=query.value.trim(); if (!value) return; emit("add", value); open.value=false; }
function keydown(e: KeyboardEvent) { if (e.key === "ArrowDown") { e.preventDefault(); open.value=true; active.value=Math.min(active.value+1, filtered.value.length-1); } else if (e.key === "ArrowUp") { e.preventDefault(); active.value=Math.max(0, active.value-1); } else if (e.key === "Enter" && open.value) { e.preventDefault(); if (active.value < filtered.value.length && filtered.value[active.value]) select(filtered.value[active.value]); else if (canAdd.value) addNew(); } else if (e.key === "Escape") open.value=false; }
function outside(e: PointerEvent) { if (root.value && !root.value.contains(e.target as Node)) open.value=false; }
onMounted(() => document.addEventListener("pointerdown", outside)); onBeforeUnmount(() => document.removeEventListener("pointerdown", outside));
</script>
<template>
  <div ref="root" class="address-suggest">
    <div class="address-suggest-control">
      <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m16 16 4 4"/></svg>
      <input :value="query" :placeholder="placeholder" autocomplete="off" role="combobox" aria-autocomplete="list" :aria-expanded="open" @focus="open=true" @click="open=true" @input="query=($event.target as HTMLInputElement).value; emit('update:modelValue', query); open=true; active=0" @keydown="keydown" />
      <button v-if="query" type="button" class="address-suggest-clear" aria-label="Clear location" @click="query=''; emit('update:modelValue',''); open=true">×</button>
      <button type="button" class="address-suggest-chevron" aria-label="Toggle suggestions" @click="open=!open"><svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg></button>
    </div>
    <div v-if="open" class="address-suggest-menu" role="listbox">
      <div class="address-suggest-caption">{{ filtered.length ? 'Suggested locations' : 'No matching locations' }}</div>
      <button v-for="(item, i) in filtered" :key="item" type="button" role="option" :aria-selected="item === modelValue" :class="['address-suggest-option', { active: i === active }]" @mouseenter="active=i" @click="select(item)"><span>{{ item }}</span><svg v-if="item === modelValue" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></button>
      <button v-if="canAdd" type="button" class="address-suggest-add" @click="addNew"><span class="address-suggest-plus">+</span><span>Add <strong>“{{ query.trim() }}”</strong><small>Save as a shared suggestion</small></span></button>
    </div>
  </div>
</template>
<style scoped>
.address-suggest{position:relative;width:100%;min-width:0}.address-suggest-control{display:flex;align-items:center;gap:9px;min-height:44px;border:1px solid #dfe7f1;border-radius:10px;background:#fff;padding:0 11px;transition:border-color .15s,box-shadow .15s}.address-suggest-control:focus-within{border-color:#0d9b6c;box-shadow:0 0 0 3px rgba(13,155,108,.10)}.address-suggest-control>svg{width:16px;height:16px;fill:none;stroke:#8190a5;stroke-width:1.8;flex:none}.address-suggest-control input{width:100%;min-width:0;border:0!important;outline:0!important;box-shadow:none!important;background:transparent!important;padding:10px 0!important;font:inherit;color:#172033}.address-suggest-control input::placeholder{color:#9aa7b6}.address-suggest-control button{display:flex;align-items:center;justify-content:center;border:0;background:transparent;color:#8290a3;cursor:pointer;padding:2px}.address-suggest-chevron svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8}.address-suggest-menu{position:absolute;z-index:80;top:calc(100% + 6px);left:0;right:0;max-height:260px;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 14px 35px rgba(15,23,42,.15);padding:5px}.address-suggest-caption{padding:7px 10px;color:#8290a3;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}.address-suggest-option{display:flex;width:100%;align-items:center;justify-content:space-between;text-align:left;border:0;border-radius:7px;padding:9px 10px;background:transparent;color:#344054;font:inherit;font-size:13px;cursor:pointer}.address-suggest-option:hover,.address-suggest-option.active{background:#f0faf6;color:#087c57}.address-suggest-option svg{width:15px;height:15px;fill:none;stroke:#079768;stroke-width:2}.address-suggest-add{display:flex;align-items:center;gap:10px;width:100%;text-align:left;margin-top:5px;padding:10px;border:0;border-top:1px solid #edf1f5;border-radius:0 0 8px 8px;background:#f8fbfa;color:#087c57;cursor:pointer;font:inherit;font-size:13px}.address-suggest-add>span:last-child{display:flex;flex-direction:column;gap:3px}.address-suggest-add small{font-size:11px;color:#8794a5}.address-suggest-plus{display:grid;place-items:center;width:27px;height:27px;border-radius:8px;background:#dff7ec;font-size:20px}.address-suggest-add strong{font-weight:700}@media(max-width:600px){.address-suggest-menu{max-height:220px}}
</style>
