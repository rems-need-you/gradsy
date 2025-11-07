document.addEventListener('DOMContentLoaded', () => {
    const gradeTableBody = document.querySelector('#gradeTable tbody');

    // Dito sa 'fetchData' function, pwedeng palitan ang data ng galing sa database.
    // Sa ngayon, static data muna para gumana.
    async function fetchData() {
        const data = [
            {
                name: "Student A",
                writtenWorks: [10, 9, 8],
                performanceTasks: [18, 22, 24],
                quarterlyAssessment: [29, 29]
            },
            {
                name: "Student B",
                writtenWorks: [10, 6, 9],
                performanceTasks: [22, 24, 23],
                quarterlyAssessment: [29, 21]
            },
            {
                name: "Student C",
                writtenWorks: [10, 8, 6],
                performanceTasks: [24, 23, 21],
                quarterlyAssessment: [21, 25]
            },
            {
                name: "Student D",
                writtenWorks: [10, 9, 7],
                performanceTasks: [23, 21, 24],
                quarterlyAssessment: [25, 29]
            },
        ];
        
        // Nag-simulate lang ng network request delay
        return new Promise(resolve => setTimeout(() => resolve(data), 500));
    }

    // Function na nagk-kuwenta ng grades base sa data
    function calculateGrades(student2) {
        // Written Works
        const wwTotal = student2.writtenWorks.reduce((sum, score) => sum + score, 0);
        const wwPercentage = (wwTotal / (10 * 3)) * 20; 
        
        // Performance Tasks
        const ptTotal = student2.performanceTasks.reduce((sum, score) => sum + score, 0);
        const ptPercentage = (ptTotal / (25 * 3)) * 60; 
        
        // Quarterly Assessment
        const qaTotal = student2.quarterlyAssessment.reduce((sum, score) => sum + score, 0);
        const qaPercentage = (qaTotal / (35 * 2)) * 20;
        
        const initialGrade = wwPercentage + ptPercentage + qaPercentage;
        
        // Ito ay halimbawa lang ng formula
        const quarterlyGrade = (initialGrade * 1.67) + 11.67;
        
        return {
            wwTotal,
            ptTotal,
            qaTotal,
            wwPercentage,
            ptPercentage,
            qaPercentage,
            initialGrade: initialGrade.toFixed(2),
            quarterlyGrade: Math.round(quarterlyGrade)
        };
    }

    // Function na naglalagay ng data sa table
    async function renderTable() {
        const students = await fetchData();
        
        gradeTableBody.innerHTML = ''; // Nililinis ang table

        students.forEach(student2 => {
            const grades2 = calculateGrades(student2);
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${student2.name}</td>
                <td>${student2.writtenWorks[0]}</td>
                <td>${student2.writtenWorks[1]}</td>
                <td>${student2.writtenWorks[2]}</td>
                <td>${grades2.wwTotal}</td>
                <td>${grades2.wwPercentage.toFixed(2)}%</td>
                <td>${student2.performanceTasks[0]}</td>
                <td>${student2.performanceTasks[1]}</td>
                <td>${student2.performanceTasks[2]}</td>
                <td>${grades2.ptTotal}</td>
                <td>${grades2.ptPercentage.toFixed(2)}%</td>
                <td>${student2.quarterlyAssessment[0]}</td>
                <td>${student2.quarterlyAssessment[1]}</td>
                <td>${grades2.qaTotal}</td>
                <td>${grades2.qaPercentage.toFixed(2)}%</td>
                <td>${grades2.initialGrade}</td>
                <td>${grades2.quarterlyGrade}</td>
            `;
            gradeTableBody.appendChild(row);
        });
    }

    renderTable();
});