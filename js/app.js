function kanbanBoard() {
    return {
        tasks: [],
        columns: [
            { id: 1, name: 'To Do' },
            { id: 2, name: 'Doing' },
            { id: 3, name: 'Done' }
        ],
        showTaskForm: false,
        editingTask: null,
        taskForm: {
            title: '',
            description: '',
            priority: 'medium',
            due_date: '',
            column_id: 1
        },
        searchTerm: '',
        priorityFilter: '',
        loading: false,

        async init() {
            await this.loadTasks();
            this.initSortable();
        },

        async loadTasks() {
            this.loading = true;
            try {
                const response = await fetch('api/tasks.php');
                if (!response.ok) throw new Error('Failed to load tasks');
                this.tasks = await response.json();
            } catch (error) {
                console.error('Error loading tasks:', error);
                alert('Failed to load tasks. Check console for details.');
            } finally {
                this.loading = false;
            }
        },

        initSortable() {
            this.$nextTick(() => {
                this.columns.forEach(column => {
                    const element = document.querySelector(`[data-column-id="${column.id}"]`);
                    if (element) {
                        new Sortable(element, {
                            group: 'tasks',
                            animation: 150,
                            onEnd: (evt) => {
                                this.handleTaskMove(evt);
                            }
                        });
                    }
                });
            });
        },

        async handleTaskMove(evt) {
            const taskId = parseInt(evt.item.dataset.taskId);
            const newColumnId = parseInt(evt.to.dataset.columnId);
            const newPosition = evt.newIndex + 1;

            try {
                const response = await fetch('api/tasks.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: taskId,
                        new_column_id: newColumnId,
                        new_position: newPosition
                    })
                });

                if (!response.ok) throw new Error('Failed to move task');
                await this.loadTasks();
            } catch (error) {
                console.error('Error moving task:', error);
                alert('Failed to move task');
                await this.loadTasks(); // Reload to reset position
            }
        },

        getFilteredTasks(columnId) {
            return this.tasks.filter(task => {
                const matchesColumn = task.column_id == columnId;
                const matchesSearch = !this.searchTerm || 
                    task.title.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                    task.description.toLowerCase().includes(this.searchTerm.toLowerCase());
                const matchesPriority = !this.priorityFilter || task.priority === this.priorityFilter;
                
                return matchesColumn && matchesSearch && matchesPriority;
            });
        },

        openTaskForm(columnId) {
            this.editingTask = null;
            this.taskForm = {
                title: '',
                description: '',
                priority: 'medium',
                due_date: '',
                column_id: columnId
            };
            this.showTaskForm = true;
        },

        selectTask(task) {
            this.editingTask = task;
            this.taskForm = { ...task };
            this.showTaskForm = true;
        },

        async saveTask() {
            const method = this.editingTask ? 'PUT' : 'POST';
            const data = this.editingTask ? this.taskForm : { ...this.taskForm };

            try {
                const response = await fetch('api/tasks.php', {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                if (!response.ok) throw new Error('Failed to save task');
                
                await this.loadTasks();
                this.closeTaskForm();
            } catch (error) {
                console.error('Error saving task:', error);
                alert('Failed to save task');
            }
        },

        async deleteTask() {
            if (!confirm('Are you sure you want to delete this task?')) return;

            try {
                const response = await fetch('api/tasks.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.editingTask.id })
                });

                if (!response.ok) throw new Error('Failed to delete task');
                
                await this.loadTasks();
                this.closeTaskForm();
            } catch (error) {
                console.error('Error deleting task:', error);
                alert('Failed to delete task');
            }
        },

        closeTaskForm() {
            this.showTaskForm = false;
            this.editingTask = null;
            this.taskForm = {
                title: '',
                description: '',
                priority: 'medium',
                due_date: '',
                column_id: 1
            };
        },

        formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString();
        }
    }
}