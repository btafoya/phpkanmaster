import { describe, it, expect } from 'vitest';

// Minimal App.Board setup matching the sortTasks implementation
const priorityWeight = { high: 3, medium: 2, low: 1 };

function sortTasks(tasks) {
    return tasks.slice().sort((a, b) => {
        const pA = priorityWeight[a.priority] || 0;
        const pB = priorityWeight[b.priority] || 0;
        if (pB !== pA) return pB - pA;
        const hasDateA = a.due_date ? 1 : 0;
        const hasDateB = b.due_date ? 1 : 0;
        if (hasDateB !== hasDateA) return hasDateB - hasDateA;
        if (a.due_date && b.due_date) {
            if (a.due_date !== b.due_date) return a.due_date.localeCompare(b.due_date);
        }
        return (a.position || 0) - (b.position || 0);
    });
}

describe('sortTasks', () => {
    it('sorts high priority before medium and low', () => {
        const tasks = [
            { id: 1, priority: 'low', position: 1 },
            { id: 2, priority: 'high', position: 2 },
            { id: 3, priority: 'medium', position: 3 },
        ];
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([2, 3, 1]);
    });

    it('sorts medium before low when priority is equal', () => {
        const tasks = [
            { id: 1, priority: 'low', position: 1 },
            { id: 2, priority: 'medium', position: 2 },
        ];
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([2, 1]);
    });

    it('sorts tasks with due dates before those without', () => {
        const tasks = [
            { id: 1, priority: 'high', due_date: null, position: 1 },
            { id: 2, priority: 'high', due_date: '2026-04-15', position: 2 },
        ];
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([2, 1]);
    });

    it('sorts by earliest due date first within same priority', () => {
        const tasks = [
            { id: 1, priority: 'high', due_date: '2026-04-20', position: 1 },
            { id: 2, priority: 'high', due_date: '2026-04-10', position: 2 },
            { id: 3, priority: 'high', due_date: '2026-04-15', position: 3 },
        ];
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([2, 3, 1]);
    });

    it('uses position as tiebreaker when priority and due date are equal', () => {
        const tasks = [
            { id: 1, priority: 'high', due_date: '2026-04-10', position: 3 },
            { id: 2, priority: 'high', due_date: '2026-04-10', position: 1 },
            { id: 3, priority: 'high', due_date: '2026-04-10', position: 2 },
        ];
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([2, 3, 1]);
    });

    it('interleaves priority and due date correctly', () => {
        const tasks = [
            { id: 1, priority: 'low', due_date: '2026-04-10', position: 1 },   // low + soonest
            { id: 2, priority: 'high', due_date: null, position: 2 },         // high + no date
            { id: 3, priority: 'high', due_date: '2026-04-12', position: 3 }, // high + soonest
            { id: 4, priority: 'medium', due_date: '2026-04-11', position: 4 }, // medium
            { id: 5, priority: 'high', due_date: '2026-04-20', position: 5 }, // high + later
        ];
        // Order: high+date (3), high+date (5), high+null (2), medium+date (4), low+date (1)
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([3, 5, 2, 4, 1]);
    });

    it('does not mutate the original array', () => {
        const original = [
            { id: 1, priority: 'low', position: 1 },
            { id: 2, priority: 'high', position: 2 },
        ];
        const originalIds = original.map(t => t.id);
        sortTasks(original);
        expect(original.map(t => t.id)).toEqual(originalIds);
    });

    it('handles unknown priority as weight 0 (sorts last)', () => {
        const tasks = [
            { id: 1, priority: 'high', position: 1 },
            { id: 2, priority: 'unknown', position: 2 },
            { id: 3, priority: 'low', position: 3 },
        ];
        const result = sortTasks(tasks);
        expect(result.map(t => t.id)).toEqual([1, 3, 2]);
    });
});
