<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite('resources/js/app.js')
</head>

<body>
    <div class="container mt-5">
        <h1>Orders</h1>
        <div>
            <table class="table table-striped text-center mt-5">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        {{-- <th scope="col">Currier Name</th> --}}
                        <th scope="col">Price</th>
                        <th scope="col">Date</th>
                        <th scope="col">Location</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                    <tr id="orders-table">
                        <td>{{ $order->id }}</td>
                        {{-- <td>{{ $order->currier->name }}</td> --}}
                        <td>{{ $order->price}}</td>
                        <td>{{ $order->date}}</td>
                        <td>{{ $order->location }}</td>
                        <td>{{ $order->status }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>