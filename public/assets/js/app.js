/**
 * phpKanMaster - Main Application
 */

window.App = {
    Api: {
        baseUrl: window.POSTGREST_URL || '/api',

        async request(endpoint, options = {}) {
            const url = `${this.baseUrl}${endpoint}`;
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Prefer': 'return=representation', // Get the object back on POST/PATCH
                    ...options.headers,
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `API Error: ${response.status}`);
            }

            return response.json();
        },

        async getTasks() {
            return this.request('/tasks?select=*&order=task_column.asc,position.asc');
        },

        async createTask(data) {
            return this.request('/tasks', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async updateTask(id, data) {
            return this.request(`/tasks?id=eq.${id}`, {
                method: 'PATCH',
                body: JSON.stringify(data),
            });
        },

        async deleteTask(id) {
            return this.request(`/tasks?id=eq.${id}`, {
                method: 'DELETE',
            });
        },

        async getCategories() {
            return this.request('/categories?select=*&order=name.asc');
        },

        async createCategory(data) {
            return this.request('/categories', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async updateCategory(id, data) {
            return this.request(`/categories?id=eq.${id}`, {
                method: 'PATCH',
                body: JSON.stringify(data),
            });
        },

        async deleteCategory(id) {
            return this.request(`/categories?id=eq.${id}`, {
                method: 'DELETE',
            });
        },

        async uploadFile(data) {
            return this.request('/task_files', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async deleteFile(id) {
            return this.request(`/task_files?id=eq.${id}`, {
                method: 'DELETE',
            });
        },
    },
    Board: {},
    Modal: {},
    DnD: {},
    Alerts: {}
};

console.log('phpKanMaster initialized');
