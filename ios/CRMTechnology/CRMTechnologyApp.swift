import SwiftUI

@main
struct CRMTechnologyApp: App {
    @StateObject private var store = StoreModel()
    var body: some Scene {
        WindowGroup {
            TabView {
                NavigationStack(path: $store.path) {
                    CatalogView().navigationDestination(for: Product.self) { ProductView(product: $0) }
                }.tabItem { Label("Discover", systemImage: "square.grid.2x2") }
                NavigationStack {
                    SavedView().navigationDestination(for: Product.self) { ProductView(product: $0) }
                }.tabItem { Label("Saved", systemImage: "heart") }
            }
            .tint(Color(red: 0.20, green: 0.34, blue: 1))
            .environmentObject(store)
            .onOpenURL { url in Task { await store.open(url) } }
        }
    }
}

struct CatalogView: View {
    @EnvironmentObject private var store: StoreModel
    var body: some View {
        List {
            Section {
                Text("Technology. Without compromise.").font(.largeTitle.bold()).padding(.vertical, 12)
                Text("Browse the shared CRM Technology catalog.").foregroundStyle(.secondary)
            }
            if let error = store.error {
                Section { Text(error).foregroundStyle(.secondary); Button("Try again") { Task { await store.search() } } }
            }
            if store.loading && store.products.isEmpty { ProgressView("Loading catalog") }
            ForEach(store.products) { product in NavigationLink(value: product) { ProductRow(product: product) } }
            if store.hasMore { Button("Load more products") { Task { await store.search(nextPage: true) } }.disabled(store.loading) }
            if !store.loading && store.error == nil && store.products.isEmpty {
                ContentUnavailableView.search(text: store.query)
            }
        }
        .navigationTitle("CRM Technology")
        .searchable(text: $store.query, prompt: "Search products or SKU")
        .onSubmit(of: .search) { Task { await store.search() } }
        .refreshable { await store.search() }
        .task { if store.products.isEmpty { await store.search() } }
    }
}

struct ProductRow: View {
    @EnvironmentObject private var store: StoreModel
    let product: Product
    var body: some View {
        HStack(spacing: 16) {
            AsyncImage(url: store.api.imageURL(product)) { image in image.resizable().scaledToFit() } placeholder: {
                Image(systemName: "shippingbox").font(.title2).foregroundStyle(.secondary)
            }.frame(width: 76, height: 76)
            VStack(alignment: .leading, spacing: 5) {
                Text(product.brand.uppercased()).font(.caption).foregroundStyle(.secondary)
                Text(product.name).font(.headline).lineLimit(3)
                Text(product.formattedPrice).font(.subheadline.bold())
                if product.isDemo { Text("Demo product").font(.caption).foregroundStyle(.orange) }
            }
        }.padding(.vertical, 8)
    }
}

struct ProductView: View {
    @EnvironmentObject private var store: StoreModel
    let product: Product
    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 20) {
                AsyncImage(url: store.api.imageURL(product)) { image in image.resizable().scaledToFit() } placeholder: {
                    Image(systemName: "shippingbox").font(.system(size: 70)).foregroundStyle(.secondary)
                }.frame(maxWidth: .infinity).frame(height: 250)
                Text(product.brand.uppercased()).font(.caption).foregroundStyle(.secondary)
                Text(product.name).font(.largeTitle.bold())
                Text(product.formattedPrice).font(.title2.bold())
                if product.isDemo { Text("Demo item. Prices and availability are test data.").foregroundStyle(.orange) }
                Text("Recheck current price, VAT treatment and stock in the storefront before ordering. Saved items can be viewed offline and may be out of date.")
                    .font(.footnote).foregroundStyle(.secondary)
                Button { store.toggleSaved(product) } label: {
                    Label(store.saved.contains(where: { $0.id == product.id }) ? "Remove from saved" : "Save for later", systemImage: "heart")
                        .frame(maxWidth: .infinity)
                }.buttonStyle(.borderedProminent)
                Link("View product and purchase", destination: store.api.productURL(product)).buttonStyle(.bordered)
                ShareLink(item: store.api.productURL(product), subject: Text(product.name)) { Label("Share product", systemImage: "square.and.arrow.up") }
            }.padding(24)
        }.navigationTitle("Product").navigationBarTitleDisplayMode(.inline)
    }
}

struct SavedView: View {
    @EnvironmentObject private var store: StoreModel
    var body: some View {
        Group {
            if store.saved.isEmpty {
                ContentUnavailableView("Your saved technology", systemImage: "heart", description: Text("Save products to build a personal shopping list you can revisit offline."))
            } else {
                List {
                    Section { Text("Saved on this device. Prices and stock may have changed.").font(.footnote).foregroundStyle(.secondary) }
                    ForEach(store.saved) { product in
                        NavigationLink(value: product) { ProductRow(product: product) }
                            .swipeActions { Button("Remove", role: .destructive) { store.toggleSaved(product) } }
                    }
                }
            }
        }.navigationTitle("Saved for later")
    }
}
