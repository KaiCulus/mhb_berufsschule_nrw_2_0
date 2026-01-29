import axios from 'axios';
import { useAuthStore } from '@/stores/authentification/auth';

const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_MHB_BACKEND_URL,
  withCredentials: true
});

// Interceptor hinzufügen
axiosInstance.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore();
    const token = authStore.idToken;

    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

export default axiosInstance;