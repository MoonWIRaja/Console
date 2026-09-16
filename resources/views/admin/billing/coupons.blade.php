@extends('layouts.admin')

@section('title')
    Billing Coupons
@endsection

@section('content-header')
    <h1>Billing Coupons<small>Create discount codes for checkout.</small></h1>
@endsection

@section('content')
    @include('admin.billing.partials.nav')

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border admin-billing-box-header">
                    <h3 class="box-title">Coupons</h3>
                    <div class="admin-billing-box-header-actions">
                        @include('admin.billing.partials.table-filter', [
                            'name' => 'status',
                            'value' => $selectedCouponStatus,
                            'options' => $couponStatusOptions,
                            'pageName' => 'page',
                            'placeholder' => 'All coupon statuses',
                        ])
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createCouponModal">+ Create Coupon</button>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Usage</th>
                                <th>Expires</th>
                                <th>Active</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($coupons as $coupon)
                                <tr>
                                    <td>
                                        <strong>{{ $coupon->code }}</strong>
                                        @if($coupon->assignedUser)
                                            <br><span class="label label-info">{{ $coupon->assignedUser->username }} only</span>
                                        @elseif($coupon->assigned_email)
                                            <br><span class="label label-default">{{ $coupon->assigned_email }} (not registered yet)</span>
                                        @endif
                                        @if($coupon->description)
                                            <br><span class="text-muted small">{{ $coupon->description }}</span>
                                        @endif
                                    </td>
                                    <td>{{ ucfirst($coupon->discount_type) }}</td>
                                    <td>
                                        @if($coupon->discount_type === \Pterodactyl\Models\BillingCoupon::TYPE_PERCENTAGE)
                                            {{ rtrim(rtrim(number_format((float) $coupon->discount_value, 2), '0'), '.') }}%
                                        @else
                                            RM {{ number_format((float) $coupon->discount_value, 2) }}
                                        @endif
                                    </td>
                                    <td>{{ $coupon->redeemed_count }} / {{ $coupon->isUnlimited() ? '∞' : $coupon->max_redemptions }}</td>
                                    <td>
                                        @if($coupon->expires_at)
                                            <span class="{{ $coupon->isExpired() ? 'text-red' : '' }}">{{ $coupon->expires_at->format('M j, Y') }}</span>
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="label label-{{ $coupon->is_active ? 'success' : 'default' }}">{{ $coupon->is_active ? 'Yes' : 'No' }}</span>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#editCoupon{{ $coupon->id }}">Edit</button>
                                        <form method="POST" action="{{ route('admin.billing.coupons.toggle', $coupon->id) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-{{ $coupon->is_active ? 'warning' : 'success' }}">{{ $coupon->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.billing.coupons.destroy', $coupon->id) }}" style="display:inline" onsubmit="return confirm('Delete coupon {{ $coupon->code }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">No coupons found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer clearfix">
                    @include('admin.billing.partials.table-pagination', ['paginator' => $coupons])
                </div>
            </div>
        </div>
    </div>

    {{-- Create Coupon - a single toggle switches between one shared public code and
         "assign to users" (multi-select and/or CSV import), which creates one unique
         personal code per matched user and emails it to them. --}}
    <div class="modal fade" id="createCouponModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form id="couponForm" method="POST" action="{{ route('admin.billing.coupons.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Create Coupon</h4>
                    </div>
                    <div class="modal-body">
                        <label class="admin-toggle-switch">
                            <input type="checkbox" id="couponAssignToggle">
                            <span class="admin-toggle-switch-track"></span>
                            <span>Assign to specific users instead of one shared code</span>
                        </label>

                        <div id="couponPublicSection">
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text" name="code" class="form-control" placeholder="Leave blank to auto-generate">
                                <p class="text-muted small">Letters, numbers, <code>. _ -</code>. Stored uppercase.</p>
                            </div>
                        </div>

                        <div id="couponAssignSection" style="display:none;">
                            <p class="text-muted small">Every recipient gets their own unique, personal coupon code by email — nobody else can use it. An email with no account yet still gets one; it activates automatically the moment that person registers with the same address.</p>
                            <div class="form-group">
                                <label>Users</label>
                                <select name="user_ids[]" class="form-control coupon-user-select" multiple style="width: 100%;">
                                    @foreach($assignableUsers as $user)
                                        <option value="{{ $user->id }}">{{ $user->username }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Or Import CSV <a href="{{ route('admin.billing.coupons.csv-template') }}">(download template)</a></label>
                                <input type="file" name="user_csv" accept=".csv,text/csv" class="form-control">
                                <p class="text-muted small">A CSV with an <code>email</code> column. Emails without an existing account still get a coupon.</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" name="description" class="form-control" placeholder="Optional internal note">
                        </div>
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Discount Type</label>
                                    <select name="discount_type" class="form-control">
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed (RM)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Discount Value</label>
                                    <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Max Redemptions</label>
                                    <input type="number" min="0" name="max_redemptions" class="form-control" placeholder="0" value="0">
                                    <p class="text-muted small" id="couponMaxRedemptionsHint">0 = unlimited.</p>
                                </div>
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label>Expires</label>
                                    <select name="expiry_months" class="form-control">
                                        <option value="never">Never</option>
                                        @foreach($expiryMonthOptions as $months)
                                            <option value="{{ $months }}">{{ $months }} Month{{ $months > 1 ? 's' : '' }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <label class="admin-toggle-switch">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="admin-toggle-switch-track"></span>
                            <span>Active</span>
                        </label>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="couponSubmitBtn">Create Coupon</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach($coupons as $coupon)
        <div class="modal fade" id="editCoupon{{ $coupon->id }}" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('admin.billing.coupons.update', $coupon->id) }}">
                    @csrf
                    @method('PATCH')
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">Edit Coupon — {{ $coupon->code }}</h4>
                        </div>
                        <div class="modal-body">
                            @if($coupon->assignedUser)
                                <p class="text-muted small">Personal coupon for <strong>{{ $coupon->assignedUser->username }}</strong> — only their account can redeem it.</p>
                            @elseif($coupon->assigned_email)
                                <p class="text-muted small">Personal coupon for <strong>{{ $coupon->assigned_email }}</strong> — no account has registered with this email yet. It will activate automatically once one does.</p>
                            @endif
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text" name="code" class="form-control" value="{{ $coupon->code }}">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control" value="{{ $coupon->description }}">
                            </div>
                            <div class="row">
                                <div class="col-xs-6">
                                    <div class="form-group">
                                        <label>Discount Type</label>
                                        <select name="discount_type" class="form-control">
                                            <option value="percentage" {{ $coupon->discount_type === 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
                                            <option value="fixed" {{ $coupon->discount_type === 'fixed' ? 'selected' : '' }}>Fixed (RM)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-xs-6">
                                    <div class="form-group">
                                        <label>Discount Value</label>
                                        <input type="number" step="0.01" min="0" name="discount_value" class="form-control" value="{{ $coupon->discount_value }}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-xs-6">
                                    <div class="form-group">
                                        <label>Max Redemptions</label>
                                        <input type="number" min="0" name="max_redemptions" class="form-control" value="{{ $coupon->max_redemptions ?? 0 }}">
                                        <p class="text-muted small">0 = unlimited. Redeemed: {{ $coupon->redeemed_count }}.</p>
                                    </div>
                                </div>
                                <div class="col-xs-6">
                                    <div class="form-group">
                                        <label>Expires</label>
                                        <select name="expiry_months" class="form-control">
                                            <option value="keep" selected>Keep current ({{ $coupon->expires_at ? $coupon->expires_at->format('M j, Y') : 'Never' }})</option>
                                            <option value="never">Never</option>
                                            @foreach($expiryMonthOptions as $months)
                                                <option value="{{ $months }}">{{ $months }} Month{{ $months > 1 ? 's' : '' }} from today</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <label class="admin-toggle-switch">
                                <input type="checkbox" name="is_active" value="1" {{ $coupon->is_active ? 'checked' : '' }}>
                                <span class="admin-toggle-switch-track"></span>
                                <span>Active</span>
                            </label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection

@section('footer-scripts')
    @parent
    <script>
        $(document).ready(function () {
            var storeUrl = @json(route('admin.billing.coupons.store'));
            var assignUrl = @json(route('admin.billing.coupons.assign'));

            var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            $('.coupon-user-select').select2({
                dropdownParent: $('#createCouponModal .modal-content'),
                placeholder: 'Search existing users or type an email…',
                tags: true,
                tokenSeparators: [',', ' '],
                // Typing an email that isn't in the picker (no account yet, or just
                // not loaded) creates a free-text tag instead of refusing the input -
                // the server resolves it by email the same way it resolves a CSV row,
                // and reports back anything that didn't match an existing account.
                createTag: function (params) {
                    var term = $.trim(params.term);
                    if (term === '' || !emailPattern.test(term)) {
                        return null;
                    }
                    return { id: term, text: term + ' (new — will get a coupon by email)', newTag: true };
                },
            });

            function applyCouponMode(isAssign) {
                $('#couponPublicSection').toggle(!isAssign);
                $('#couponAssignSection').toggle(isAssign);
                $('#couponForm').attr('action', isAssign ? assignUrl : storeUrl);
                $('#couponSubmitBtn').text(isAssign ? 'Send Coupons' : 'Create Coupon');
                $('#couponMaxRedemptionsHint').text(isAssign ? 'Per user. 0 = unlimited.' : '0 = unlimited.');
            }

            $('#couponAssignToggle').on('change', function () {
                applyCouponMode($(this).is(':checked'));
            });

            // Reopening after a validation-error redirect should not silently fall
            // back to "public code" mode if the admin had picked "assign" - reflect
            // whatever the toggle's current (browser-restored) state actually is.
            applyCouponMode($('#couponAssignToggle').is(':checked'));

            $('#createCouponModal').on('hidden.bs.modal', function () {
                $('#couponForm')[0].reset();
                $('.coupon-user-select').val(null).trigger('change');
                applyCouponMode(false);
            });
        });
    </script>
@endsection
