import axios from 'axios';

const axiosInstance = axios.create({
  baseURL: import.meta.env.VITE_MHB_BACKEND_URL,
  withCredentials: true
});

export default axiosInstance;