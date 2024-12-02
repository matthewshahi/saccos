@extends('layouts.app')

@section('content')

<style>
 .text-blackbold{
    font-weight:700;
 }
 .text-redbold{
    font-weight:700;
    color:red;
 }
.footer{
   

 
}
#msg{
    margin-bottom:20px;
    margin-top:20px;
}
#submitbtn{
    background-color:rgb(2, 50, 106);
}
    </style>

<div class="text-center mb-4">
    <h1>{{$publication_name}}</h1>
</div>

<!-- Row 1: 2 Static Items -->




<div class="static-row">
 




<div class="row justify-content-center border-bottom">
<div class="col-6 col-md-2">
<img  width="129" src="/assets/img/mpesa-b.jpg" class="img-size">
</div>
</div>



<div class="row justify-content-center pt-4 formi">
<div class="col-12 col-md-5 border-right">

<p class="d-block d-md-none1 text-blackbold"> Method 1</p>
<p>
Enter your M-PESA registered phone number below and click Pay Now then check your mobile phone handset for an instant payment request from Safaricom M-PESA.
</p>
<p>
<a target="_blank" style="color:red; text-decoration:underline" href="https://epaper.nairobilawmonthly.com/privacy_terms_and_conditions" target="_blank" data-mrf-link="https://epaper.nairobilawmonthly.com/privacy_terms_and_conditions" cmp-ltrk="Article Container" cmp-ltrk-idx="0">
<strong>By Clicking Pay Now or make payment via the Paybill 670361, you agree to the terms and conditions.</strong>
</a>
</p>
<form action="/consumer/stk/initiate/{{ uniqid() }}" method="GET" id="form" class="mt-5 newstyle">
    <input type="hidden" id="uniq" name="uniq" value="{{ uniqid()}}"/>
    <input type="hidden" id="orderno" name="orderno" value="{{$ordernumber}}"/>
<input type="text" id="phone" name="phone" value="" class="w-75 " placeholder="Enter your phone no. eg 0722000100" required="">
<input type="hidden" value="{{$packageid}}" id="packageid" name="packageid">
<input type="hidden" value="{{$publicationid}}" id="publicationid" name="publicationid">
<input type="hidden" id="user_id" name="user_id" value="{{$user_id}}" class="w-75 " placeholder="Enter your phone no. eg 0722000100" autocomplete="off" required="">
<button type="button" id="submitbtn" class="newslettericon  text-white border-0">Submit</button>
</form>
<div id="msg"></div>
</div>
<div class="col-12 col-md-5">
<p class="d-block d-md-none1 text-blackbold"> Method 2</p>
<ul class="spacing-btw">
<li>Go to Safaricom M-PESA Menu,<span class="text-blackbold"> Select Lipa Na Mpesa</span></li>
<li> Select <span class="text-blackbold"> Pay Bill</span></li>
<li> Enter <span class="text-blackbold">670361, </span>as business number and press ”OK”</li>
<li>Select <span class="text-blackbold"> Enter Account no</span></li>
<li> Enter your Order number
<span class="text-redbold">{{$ordernumber}}</span> and press “OK”</li>
<li> Enter<span class="text-blackbold"> Your Amount,</span>
<span class="text-redbold"> {{$amount}} </span> and press “OK”</li>
<li> Enter Your<span class="text-blackbold">Mpesa Pin</span> and press “OK”</li>
<li> Confirm all the details are correct and press ”OK”</li>
</ul>
</div>
</div>





</div>

 


@endsection

@section('scripts')
<script>
    $(document).ready(function(){
        
            worker();

            $('#submitbtn').click(function() {

                var phone = $('#phone').val();
                var user_id = $('#user_id').val();
                var data = $('#form').serialize();
                var uniq = $('#uniq').val();
                if(phone.length === 0)
                {
                    $('#msg').css("color","red").text('Please provide your phone number');
                    return
                }

                $(this).html("Processing..");

                $.ajax({
                    type : 'POST',
                    url : '/consumer/stk/initiate/'+uniq,
                    data : data, // our data object
                    headers: {  
                 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                    success : function (data,status,xhr) {
                       
                        $("#submitbtn").html("Submit");
                        $('#msg').css("color","green").text('Check your phone for a confirmation prompt');
                    },
                    error : function (xhr,status,error) {
                        $("#submitbtn").html("Submit");
                        $('#msg').css("color","red").text("Error occured processing.Please try again");
                         
                        
                    }
                });
            });
        });


        function worker() {

            var phone = $("#phone").val();
            var id =$("#orderno").val();
            var contextPath = '/stk/check/'+id;
            var url =$("#publicationid").val()==2?'https://epaper.nairobilawmonthly.com':'https://epaper.nairobibusinessmonthly.com';
            $.ajax({
                url: contextPath,
                type: 'Get',
                headers: {  
                 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (data) {
                    console.log(data);
                    if (!$.trim(data))
                        return;

                    if (data == 'good')
                        window.location.href = url;

                },
                complete: function () {
                    // Schedule the next request when the current one's complete
                    setTimeout(worker, 5000);
                }
            });
        }
 
    
</script>
@endsection
