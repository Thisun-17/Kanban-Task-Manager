function kanbanBoard() {
    return {
        // Our data
        tasks: [
            { id: 1, title: 'First Task', column: 'todo' },
            { id: 2, title: 'Second Task', column: 'doing' },
            { id: 3, title: 'Third Task', column: 'done' }
        ],
        
        // Form data
        newTaskTitle: '',
        showForm: false,
        
        // Methods (functions)
        addTask() {
            if (this.newTaskTitle.trim()) {
                this.tasks.push({
                    id: Date.now(), // Simple ID generation
                    title: this.newTaskTitle,
                    column: 'todo'
                });
                this.newTaskTitle = ''; // Clear the input
                this.showForm = false; // Hide the form
            }
        },
        
        deleteTask(taskId) {
            this.tasks = this.tasks.filter(task => task.id !== taskId);
        }
    }
}