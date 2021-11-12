<div class="row">
    <div class="col-md-12">
        <table class="table table-responsive table-bordered">
            <thead>
                <tr>
                    <th>Ticket Number</th>
                    <th>Bundle Name</th>
                    <th>Ticket Valid</th>
                    <th>Ticket Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase as $value)
                    <tr>
                        <td>{{$value->ticket_number}}</td>
                        <td>{{$value->name}}</td>
                        <td>{{ucfirst($value->is_valid)}}</td>
                        <td>{{($value->status == "sold") ? "Active" : ucfirst($value->status)}}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<style>
    .btn-primary {
        display: none !important;
    }
</style>
