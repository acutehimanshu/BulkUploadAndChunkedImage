<!DOCTYPE html>
<html>
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Product Import & Chunked upload</title>
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

    <hr> Task 2
    <hr>
    <h1>Chunked Upload</h1>

    <input type="file" id="fileInput">
    <button id="startBtn">Start Upload</button>

    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const CHUNK_SIZE = 1024 * 1024; // 1MB

        document.getElementById('startBtn').addEventListener('click', async () => {
            const file = document.getElementById('fileInput').files[0];
            if (!file) return alert('Choose a file');

            // helper: sha256 checksum
            async function hashFile(f) {
                const buf = await f.arrayBuffer();
                const hash = await crypto.subtle.digest('SHA-256', buf);
                return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2,'0')).join('');
            }
            const checksum = await hashFile(file);

            // initiate
            let form = new FormData();
            form.append('file_name', file.name);
            form.append('total_size', file.size);
            form.append('mime_type', file.type);
            form.append('checksum', checksum);

            let initRes = await fetch("{{ route('uploads.initiate') }}", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": CSRF_TOKEN },
                body: form
            });
            let initJson = await initRes.json();
            let uploadId = initJson.upload_id;

            // send chunks
            let index = 0;
            while (index * CHUNK_SIZE < file.size) {
                const start = index * CHUNK_SIZE;
                const end = Math.min(file.size, start + CHUNK_SIZE);
                const chunk = file.slice(start, end);

                let chunkForm = new FormData();
                chunkForm.append('upload_id', uploadId);
                chunkForm.append('chunk_index', index);
                chunkForm.append('chunk', chunk);

                let r = await fetch("{{ route('uploads.chunk') }}", {
                    method: "POST",
                    headers: { "X-CSRF-TOKEN": CSRF_TOKEN },
                    body: chunkForm
                });
                let jr = await r.json();
                console.log("chunk", index, jr);
                index++;
            }

            // complete
            let completeForm = new FormData();
            completeForm.append('upload_id', uploadId);
            let comp = await fetch("{{ route('uploads.complete') }}", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": CSRF_TOKEN },
                body: completeForm
            });
            let compJson = await comp.json();
            console.log("complete:", compJson);
            alert("Upload complete!");
        });
    </script>

</body>
</html>
