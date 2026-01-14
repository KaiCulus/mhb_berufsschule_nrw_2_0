<script setup>
  import { useRouter } from 'vue-router'

  const router = useRouter()
  const emit = defineEmits(['closeMenu'])
  const props = defineProps({
    name: {
      type: String,
      required: true
    },
    route: {
      type: String,
      required: true
    }
  })

  const goToEntry = () => {
    router.push(props.route)
    emit('closeMenu')
  } 

</script>

<template>
  <div class="menu-entry" v-on:click="goToEntry">
    {{name}}
  </div>
</template>

<style scoped>
  .menu-entry {
    width: 100%;
    padding: 12px 20px;
    color: white;
    background-color: #444;
    text-align: left;
    cursor: pointer;
    transition: background-color 0.2s;
  }

  .menu-entry:hover {
    background-color: #555;
  }
</style>