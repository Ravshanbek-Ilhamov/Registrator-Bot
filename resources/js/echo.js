import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
// window.Echo.channel('orders')
//     .listen('OrderStatusUpdated', (e) => {
//         if (!e.orders || !Array.isArray(e.orders)) {
//             console.error('Invalid orders data:', e.orders);
//             return;
//         }

//         const ordersTableBody = document.querySelector('#orders-table tbody');
//         ordersTableBody.innerHTML = '';

//         e.orders.forEach(order => {
//             const statusClass = order.status === 'pending' ? 'table-danger' : 'table-success';
//             const row = `
//                 <tr class="${statusClass}">
//                     <th scope="row">${order.id}</th>
//                     <td>${order.price}</td>
//                     <td>${order.date}</td>
//                     <td>${order.location}</td>
//                     <td>${order.status}</td>
//                 </tr>
//             `;
//             ordersTableBody.insertAdjacentHTML('beforeend', row);
//         });
//     });

window.Echo.channel('order')
    .listen('OrderStatusUpdated', (event) => {
        console.log('Order received:', event.order);
        const order = event.order;
        const table = document.getElementById('orders-table');
        // const row = table.insertRow();
        table.innerHTML = `
            <td>${order.id}</td>
            <td>${order.price}</td>
            <td>${order.date}</td>
            <td>${order.location}</td>
            <td>${order.status}</td>
        `;
    });
