import axios from 'axios';

export const API_BASE = '/api';

export const SYSTEM_MAINTENANCE_MSG = 
  "System Maintenance: Our high-performance AI processing nodes are currently undergoing optimization. Our engineering team is actively working on it. Access will be restored shortly. We appreciate your patience!";

export const sanitizeError = (msg) => {
  if (!msg) return msg;
  const lower = String(msg).toLowerCase();
  if (
    lower.includes('balance is too low') || 
    lower.includes('plans & billing') || 
    lower.includes('purchase credits') || 
    lower.includes('credit balance') ||
    lower.includes('credit_balance_too_low')
  ) {
    return SYSTEM_MAINTENANCE_MSG;
  }
  return msg;
};

// Centralized Axios instance with automatic token authentication
const apiClient = axios.create({
  baseURL: API_BASE,
  timeout: 180000,
});

apiClient.interceptors.request.use((config) => {
  try {
    const saved = localStorage.getItem('user_session');
    if (saved) {
      const user = JSON.parse(saved);
      if (user && user.token) {
        config.headers.Authorization = `Bearer ${user.token}`;
      }
    }
  } catch (e) {
    // Ignore JSON parse errors
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response && error.response.data && error.response.data.error) {
      error.message = sanitizeError(error.response.data.error);
    } else if (error.message) {
      error.message = sanitizeError(error.message);
    }
    return Promise.reject(error);
  }
);

export default apiClient;
