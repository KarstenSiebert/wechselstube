<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Inertia\Inertia;
use App\Models\Contact;
use Illuminate\Http\Request;
use App\Rules\CardanoAddress;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function index() 
    {
        $contacts = Contact::where('user_id', auth()->id())->paginate(10);

        $contactList = [];

        foreach ($contacts as $contact) {
            $contactList[] = ['id' => $contact->id, 'name' => $contact->name, 'address' => $contact->address];
        }

        $links = $contacts->linkCollection()->map(function ($link) {

            if ($link['label'] === '&laquo; Previous') {
                $link['label'] = 'Prev';

            } elseif ($link['label'] === 'Next &raquo;') {
                $link['label'] = 'Next';
            }
            
            return $link;
        });

        $totalPages = $links->filter(fn($link) => is_numeric($link['label']))->count();

        $currentPage = $links->firstWhere('active', true)['page'] ?? 1;

        $links = $links->filter(function ($link) use ($totalPages, $currentPage) {
    
            if ($totalPages <= 1 && in_array($link['label'], [__('Prev'), __('Next')])) {
                return false;
            }

            if ($link['label'] === __('Prev') && $currentPage === 1) {
                return false;
            }

            if ($link['label'] === __('Next') && $currentPage === $totalPages) {
                return false;
            }

            return true;

        })->values();

        // dd($contactList);
         
        return Inertia::render('contacts/Contacts', [
            'contacts' => [
                'data' => $contactList,
                'links' => $links,
                'meta'  => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                ]
            ]
        ]);        
    }

    public function create() 
    {
        return Inertia::render('contacts/Create', [
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());

        $user_id = auth()->id();
      
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255', Rule::unique('contacts')->where(fn ($query) => $query->where('user_id', $user_id))],
            'address' => ['required', new CardanoAddress()],
        ]);
       
        try {
            $contact = Contact::create([
                'user_id'   => $user_id,
                'name'      => $request->name,
                'address'   => $request->address,
            ]);

            return redirect('contacts')->with('success', __('contact_created_successfully'));

        } catch (Exception $e) {

        }
    
        return redirect('contacts')->with('error', __('contact_not_created'));
    }

    public function edit(Contact $contact)
    {                
        $contAct = ['id' => $contact->id, 'name' => $contact->name, 'address' => $contact->address];

        return Inertia::render('contacts/Edit', [
            'contact' => $contAct
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $validated = $request->validate([            
            'address' => ['required', new CardanoAddress()]            
        ]);
        
        $error   = 'error';
        $message = 'validation.contact_not_updated';

        if ($contact = Contact::where('id', $contact->id)->where('user_id', auth()->id())->update($validated)) {
            $error   = 'success';
            $message = 'validation.contact_updated_successfully';
        }

        return redirect('contacts')->with($error, __($message));
    }

    public function destroy(Contact $contact)
    {
        $error   = 'error';
        $message = 'validation.contact_not_deleted';

        if ($contact = Contact::where('id', $contact->id)->where('user_id', auth()->id())->delete()) {
            $error   = 'success';
            $message = 'validation.contact_deleted_successfully';
        }

        return back()->with($error, __($message));
    }

    public function search(Request $request)
    {        
        $q = $request->input('q', '');

        $contacts = Contact::query()
            ->where('user_id', auth()->id())
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'address']);
            
        return response()->json($contacts);
    }

}
