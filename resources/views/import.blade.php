<!DOCTYPE html>
<html>
<head>
    <title>Product Import</title>
</head>
<body>
    <h1>Upload Product CSV</h1>

    @if(session('message'))
        <p style="color:green">{{ session('message') }}</p>
    @endif

    <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Upload</button>
    </form>

    <h2>Recent Imports</h2>
    <table border="1" cellpadding="5">
        <tr>
            <th>File</th>
            <th>Total</th>
            <th>Imported</th>
            <th>Updated</th>
            <th>Invalid</th>
            <th>Duplicates</th>
            <th>Status</th>
            <th>Created At</th>
        </tr>
        @forelse($summaries as $summary)
            <tr>
                <td>{{ $summary->file_name }}</td>
                <td>{{ $summary->total }}</td>
                <td>{{ $summary->imported }}</td>
                <td>{{ $summary->updated }}</td>
                <td>{{ $summary->invalid }}</td>
                <td>{{ $summary->duplicates }}</td>
                <td>{{ $summary->is_completed ? 'Completed' : 'Processing...' }}</td>
                <td>{{ $summary->created_at->format('Y-m-d H:i') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8">No imports yet.</td>
            </tr>
        @endforelse
    </table>
</body>
</html>
