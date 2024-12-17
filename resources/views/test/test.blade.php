<form action="{{ route('temp.import_capital') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <label for="capital_file">Upload Capital File:</label>
    <input type="file" name="capital_file" accept=".xls,.xlsx" required>
    <button type="submit">Import Capital</button>
</form>