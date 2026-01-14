import { defineStore } from 'pinia'

export const useUserstore = defineStore('userstore', {
  state: () => ({
    name: "",
    favorites: [],
  }),
  actions: {
    getFavorites(favoritedata){
        this.favorites = favoritedata
    },
    getName(namedata){
        this.name = namedata
    }
  },
  persist: true, // Login-Status persistieren (optional)
})

