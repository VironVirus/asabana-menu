let menuData = { food: [], drinks: [], specials: [] };
const listContainer = document.getElementById('itemList');
const MAX_IMAGE_SIZE = 2 * 1024 * 1024; // 2MB
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

// Add authorized email addresses here
const ALLOWED_EMAILS = ['admin@asabanahotel.com', 'owner@example.com'];

/**
 * Netlify Identity Logic
 */
if (window.netlifyIdentity) {
    window.netlifyIdentity.on("init", user => {
        if (!user) {
            window.netlifyIdentity.open(); // Prompt login if not authenticated
        } else {
            showAdmin();
        }
    });
    window.netlifyIdentity.on("login", user => {
        window.netlifyIdentity.close();
        showAdmin();
    });
    window.netlifyIdentity.on("logout", () => {
        window.location.reload();
    });
}

function showAdmin() {
    const user = netlifyIdentity.currentUser();
    if (user && ALLOWED_EMAILS.includes(user.email)) {
        const content = document.getElementById('adminContent');
        if (content) content.classList.remove('hidden');
        loadData();
    } else {
        alert("Access Denied: Your email is not authorized to access this panel.");
        netlifyIdentity.logout();
    }
}


/**
 * Load the current menu data from the repo
 */
async function loadData() {
    try {
        const res = await fetch('menu.json');
        if (!res.ok) throw new Error("Failed to load menu.json");
        menuData = await res.json();
        render();
    } catch (e) {
        console.error(e);
        listContainer.innerHTML = `<p class="text-red-500 py-8 text-center">Error: ${e.message}</p>`;
    }
}

/**
 * Render the list of items for editing
 */
function render(filter = '') {
    const allItems = [...menuData.food, ...menuData.drinks, ...menuData.specials];
    listContainer.innerHTML = '';
    
    const filtered = allItems.filter(i => i.title.toLowerCase().includes(filter.toLowerCase()));
    
    if (filtered.length === 0) {
        listContainer.innerHTML = '<p class="py-8 text-center text-stone-400">No items found.</p>';
        return;
    }

    filtered.forEach(item => {
        const row = document.createElement('div');
        row.className = 'py-3 flex items-center justify-between group';
        row.innerHTML = `
            <div class="flex items-center gap-4">
                <img src="${item.img}" class="w-10 h-10 object-cover rounded shadow-sm" onerror="this.src='images/logo.jpg'">
                <div>
                    <p class="font-semibold text-sm">${item.title}</p>
                    <p class="text-[10px] text-stone-400 uppercase tracking-tighter">${item.category} • ₦${item.price.toLocaleString()}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <button onclick="openModal('${item.id}')" class="text-xs font-bold text-amber-600 hover:underline">Edit</button>
                <button onclick="deleteItem('${item.id}')" class="text-xs font-bold text-red-500 hover:underline">Delete</button>
            </div>
        `;
        listContainer.appendChild(row);
    });
}

/**
 * Save the entire menu.json back to GitHub
 */
async function commitChanges() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerText = 'Publishing...';

    const user = netlifyIdentity.currentUser();
    const token = await user.jwt();

    try {
        const res = await fetch('/.netlify/functions/updateMenu', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({
                filePath: 'menu.json',
                content: menuData,
                message: `Admin update: ${new Date().toISOString()}`
            })
        });
        if (res.ok) alert('Success! The site is rebuilding with your changes.');
        else throw new Error("Update failed");
    } catch (e) { alert('Error: ' + e.message); }
    
    btn.disabled = false;
    btn.innerText = 'Publish Changes';
}

document.getElementById('editForm').onsubmit = async (e) => {
    e.preventDefault();
    const id = document.getElementById('itemId').value;
    const fileInput = document.getElementById('itemFile');
    let imgPath = document.getElementById('imagePathDisplay').innerText;

    const user = netlifyIdentity.currentUser();
    const token = await user.jwt();

    // Handle Image Upload
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        
        // Validation
        if (file.size > MAX_IMAGE_SIZE) return alert("File too large. Max 2MB.");
        if (!ALLOWED_TYPES.includes(file.type)) return alert("Invalid file type.");

        const reader = new FileReader();
        reader.readAsDataURL(file);
        await new Promise(r => reader.onload = r);
        
        imgPath = `images/${file.name}`;
        await fetch('/.netlify/functions/updateMenu', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: JSON.stringify({
                filePath: imgPath,
                content: reader.result.split(',')[1],
                isImage: true,
                message: `Upload: ${file.name}`
            })
        });
    }

    const category = document.getElementById('itemCategory').value;
    const newItem = {
        id, 
        title: document.getElementById('itemName').value,
        price: parseInt(document.getElementById('itemPrice').value),
        category,
        img: imgPath
    };

    // Update local state
    Object.keys(menuData).forEach(cat => menuData[cat] = menuData[cat].filter(i => i.id !== id));
    
    let targetCat = 'food';
    if (['alcohol', 'sodas', 'malt-energy', 'water', 'juices-yoghurt-tea'].includes(category)) targetCat = 'drinks';
    if (category === 'specials') targetCat = 'specials';
    
    menuData[targetCat].push(newItem);

    render();
    closeModal();
};

window.deleteItem = (id) => {
    if(confirm('Delete this item?')) {
        Object.keys(menuData).forEach(cat => menuData[cat] = menuData[cat].filter(i => i.id !== id));
        render();
    }
};

document.getElementById('adminSearch').oninput = (e) => render(e.target.value);
// loadData() is now called inside showAdmin() after identity check


function closeModal() { document.getElementById('editModal').style.display = 'none'; }
window.openModal = function(id = '') {
    const item = id ? [...menuData.food, ...menuData.drinks, ...menuData.specials].find(i => i.id === id) : null;
    document.getElementById('itemId').value = id || 'item-' + Date.now();
    document.getElementById('itemName').value = item?.title || '';
    document.getElementById('itemPrice').value = item?.price || '';
    document.getElementById('itemCategory').value = item?.category || 'swallow';
    document.getElementById('imagePathDisplay').innerText = item?.img || '';
    document.getElementById('editModal').style.display = 'flex';
};